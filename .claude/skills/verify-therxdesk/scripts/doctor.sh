#!/usr/bin/env bash
# Read-only: is this instance worth driving? Prints one line per check and
# exits 1 if any check fails. Changes nothing.
source "$(dirname "$0")/env.sh"
set +e

failed=0
ok() { echo "ok    $*"; }
bad() { echo "FAIL  $*"; failed=1; }
note() { echo "note  $*"; }

app_id="$(compose ps --status running -q app 2>/dev/null)"

if [ -z "$app_id" ]; then
    bad "app container is not running (run launch.sh)"
else
    health="$(docker inspect -f '{{.State.Health.Status}}' "$app_id")"
    if [ "$health" = "healthy" ]; then ok "app container healthy"; else bad "app container is $health"; fi

    served="$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html"}}{{.Source}}{{end}}{{end}}' "$app_id")"
    if [ "$served" = "$MAIN_CHECKOUT" ]; then ok "stack serves $MAIN_CHECKOUT"; else bad "stack serves $served, not $MAIN_CHECKOUT"; fi

    branch="$(git -C "$MAIN_CHECKOUT" branch --show-current)"
    note "served code: branch ${branch:-<detached>} at $(git -C "$MAIN_CHECKOUT" rev-parse --short HEAD), $(git -C "$MAIN_CHECKOUT" status --porcelain | wc -l) uncommitted paths"

    if [ "$HERE_TOPLEVEL" != "$MAIN_CHECKOUT" ]; then
        bad "you are in $HERE_TOPLEVEL, but the stack serves $MAIN_CHECKOUT: a run proves the served code, not yours"
    fi
fi

if curl -fsS -o /dev/null "$BASE_URL/up"; then ok "$BASE_URL/up answers"; else bad "$BASE_URL/up does not answer"; fi

if curl -fsS -o /dev/null "$VITE_URL/@vite/client"; then ok "Vite dev server answers at $VITE_URL"; else bad "Vite dev server down: pages load no JS"; fi

if [ -f "$MAIN_CHECKOUT/public/hot" ]; then
    ok "public/hot points the app at Vite ($(cat "$MAIN_CHECKOUT/public/hot"))"
else
    note "no public/hot: the app serves the last npm run build, not your current frontend"
fi

if curl -fsS "$BASE_URL/login" | grep -q 'data-page'; then ok "login page renders"; else bad "login page does not render"; fi

# The clock must have one time source (see the project's dev notes): a skewed
# clock makes Passport and cache locks misbehave.
container_now="$(compose exec -T app date +%s 2>/dev/null)"
if [ -n "$container_now" ]; then
    skew=$(( container_now - $(date +%s) ))
    if [ "${skew#-}" -le 2 ]; then ok "clock skew ${skew}s"; else bad "clock skew ${skew}s between host and container"; fi
fi

if "$(dirname "$0")/fixture.sh" show >/dev/null 2>&1; then
    ok "fixture Owner exists (fixture.sh show)"
else
    bad "no fixture Owner (run fixture.sh up)"
fi

if docker image inspect "$PLAYWRIGHT_IMAGE" >/dev/null 2>&1; then
    ok "Playwright image present"
else
    note "Playwright image missing: the first drive.sh pulls it (about 1 GB)"
fi

if docker volume inspect "$NODE_MODULES_VOLUME" >/dev/null 2>&1; then ok "node_modules volume present"; else bad "volume $NODE_MODULES_VOLUME missing (launch.sh creates it)"; fi

exit "$failed"
