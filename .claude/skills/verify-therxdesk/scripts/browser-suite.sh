#!/usr/bin/env bash
# Runs the Pest browser suite (tests/Browser) on this machine.
#
# The Alpine dev image cannot: the plugin's bundled Chromium is glibc-only, and
# the plugin also needs a Playwright at least as new as the version it pins
# (1.63 when this was written). So this builds a throwaway image from the
# official Playwright image at the repo's installed version plus PHP 8.5, copies
# the checkout to a scratch folder WITHOUT public/hot (which would send the test
# page at the running Vite dev server), and runs the suite against the dev
# stack's pgsql and redis.
#
#   browser-suite.sh                                      the whole suite, three processes
#   browser-suite.sh tests/Browser/BillingPageTest.php    only that file
#
# Screenshots and traces land in $STATE_DIR/browser-suite/repo/tests/Browser/.
# CI runs the same suite (`php artisan test tests/Browser` locally does not: the
# testsuite list holds Unit and Feature only). Never writes to the checkout.
source "$(dirname "$0")/env.sh"

DOCKERFILE="$(dirname "$0")/browser-suite.Dockerfile"
WORK="$STATE_DIR/browser-suite"
REPO="$WORK/repo"

command -v rsync >/dev/null || {
    echo "[browser-suite] rsync is not installed on this machine" >&2
    exit 1
}

if [ ! -f "$MAIN_CHECKOUT/public/build/manifest.json" ]; then
    echo "[browser-suite] public/build is missing: build the assets first (npm run build)" >&2
    exit 1
fi

# The version the tests run on, so the image always carries the matching
# browser build. Never hard-code it here.
playwright_version="$(
    app_run node -e 'process.stdout.write(require("playwright/package.json").version)'
)"
image="rxdesk-browser-suite:${playwright_version}"

if ! docker image inspect "$image" >/dev/null 2>&1; then
    echo "[browser-suite] building $image (PHP 8.5 on the Playwright $playwright_version image)"
    docker build -q -t "$image" \
        --build-arg "PLAYWRIGHT_VERSION=$playwright_version" \
        -f "$DOCKERFILE" "$(dirname "$DOCKERFILE")"
fi

project="$(compose config --format json | python3 -I -c 'import json,sys; print(json.load(sys.stdin)["name"])')"

echo "[browser-suite] copying the checkout to $REPO (no .git, node_modules, vendor, public/hot)"
mkdir -p "$REPO"
rsync -a --delete \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='public/hot' \
    --exclude='storage/logs/*' \
    "$MAIN_CHECKOUT/" "$REPO/"

# Pest's test impact analysis (tests/Pest.php: tia()->locally()) needs a branch,
# and the copy has no .git: give it one so the worker can resolve it. Its HEAD is
# never updated, so every copy looks fully changed and TIA runs every test.
if [ ! -d "$REPO/.git" ]; then
    git -C "$REPO" init -q
    git -C "$REPO" -c user.email=verify@example.com -c user.name=verify add -A
    git -C "$REPO" -c user.email=verify@example.com -c user.name=verify commit -qm baseline
fi
rm -rf "$REPO/.pest"

run_pest() {
    docker run --rm -u "$(id -u):$(id -g)" \
        --network "${project}_default" \
        -e HOME=/tmp \
        -e DB_HOST=pgsql \
        -e REDIS_HOST=redis \
        -v "$REPO:/var/www/html" \
        -v "${project}_app-node-modules:/var/www/html/node_modules" \
        -v "${project}_app-vendor:/var/www/html/vendor" \
        -v "$MAIN_CHECKOUT/.env.ci:/var/www/html/.env:ro" \
        -v "$MAIN_CHECKOUT/.env.ci:/var/www/html/.env.testing:ro" \
        -w /var/www/html \
        "$image" \
        ./vendor/bin/pest "$@" --compact --parallel --processes=3
}

if [ "$#" -gt 0 ]; then
    status=0
    for test_path in "$@"; do
        echo "[browser-suite] running $test_path"
        run_pest "$test_path" || status=1
    done
    exit "$status"
fi

echo "[browser-suite] running on Playwright $playwright_version"
run_pest tests/Browser

echo "[browser-suite] screenshots and traces: $REPO/tests/Browser/"
