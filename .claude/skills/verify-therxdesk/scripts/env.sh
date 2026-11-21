# Shared settings for the verify-therxdesk scripts. Sourced, never run.
#
# Every script works on the dev stack of the MAIN checkout: that is the only
# checkout the running app serves (its bind mount). From a git worktree the
# scripts still drive the main checkout's stack; doctor.sh says when the code
# you are in is not the code being served.

set -euo pipefail

SKILL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HERE_TOPLEVEL="$(git -C "$SKILL_DIR" rev-parse --show-toplevel)"
MAIN_CHECKOUT="$(dirname "$(git -C "$SKILL_DIR" rev-parse --path-format=absolute --git-common-dir)")"

# Paths inside the app container and the Playwright container.
SKILL_IN_APP="/var/www/html/${SKILL_DIR#"$HERE_TOPLEVEL"/}"
SKILL_IN_WORK="/work/${SKILL_DIR#"$HERE_TOPLEVEL"/}"

BASE_URL="${VERIFY_BASE_URL:-http://localhost:8000}"
VITE_URL="${VERIFY_VITE_URL:-http://localhost:5173}"
PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v1.65.1-noble"
NODE_MODULES_VOLUME="therxdesk_app-node-modules"

# Evidence lives outside the repo and outside the containers, so cleanup
# never touches it.
EVIDENCE_ROOT="${VERIFY_EVIDENCE_ROOT:-$HOME/.cache/verify-therxdesk/runs}"
# What launch.sh started, so cleanup.sh stops only that.
STATE_DIR="$HOME/.cache/verify-therxdesk/state"

# The email the register journey signs up, as a LIKE pattern; `%` is the part
# the run makes unique. fixture.php's `guests` command removes them by it.
GUEST_EMAIL_LIKE="verify-signup-%@example.com"

# Selling mode (selling.sh): the app runs with SELF_HOSTED=false and a Stripe
# CLI listener forwards sandbox webhooks to it, through the overlay below.
# .env is never edited. While SELLING_MARKER exists every script includes the
# overlay, so `compose stop`, `compose run` and `compose up` all see the same
# services. The webhook secret lives only in SELLING_SECRET_FILE (mode 600).
STRIPE_CLI_IMAGE="stripe/stripe-cli:v1.55.14"
SELLING_OVERLAY="$SKILL_DIR/scripts/compose.selling.yaml"
SELLING_MARKER="$STATE_DIR/selling"
SELLING_SECRET_FILE="$STATE_DIR/stripe-webhook.env"
INTERNAL_SECRET_FILE="$STATE_DIR/internal-api.env"

compose() {
    if [ -f "$SELLING_MARKER" ]; then
        docker compose --progress quiet --project-directory "$MAIN_CHECKOUT" \
            -f "$MAIN_CHECKOUT/compose.yaml" -f "$MAIN_CHECKOUT/compose.override.yaml" -f "$SELLING_OVERLAY" "$@"
        return
    fi

    docker compose --progress quiet --project-directory "$MAIN_CHECKOUT" "$@"
}

# wait_for <what> <seconds> <command...>: retry the command every 2 seconds
# until it succeeds; on timeout print the app's last log lines and exit 1.
wait_for() {
    local what="$1" seconds="$2"
    shift 2
    local waited=0
    until "$@" >/dev/null 2>&1; do
        waited=$((waited + 2))
        if [ "$waited" -ge "$seconds" ]; then
            echo "[wait] FAILED: $what not ready after ${seconds}s" >&2
            compose logs --tail=40 app >&2
            exit 1
        fi
        sleep 2
    done
    echo "[wait] ready: $what"
}

app_healthy() {
    [ "$(docker inspect -f '{{.State.Health.Status}}' "$(compose ps -q app)")" = "healthy" ]
}

# Wait until the stack is usable after a start or a recreate.
wait_until_usable() {
    wait_for "app container healthy (entrypoint done)" 900 app_healthy
    wait_for "GET $BASE_URL/up" 120 curl -fsS "$BASE_URL/up"
    wait_for "Vite dev server at $VITE_URL" 120 curl -fsS "$VITE_URL/@vite/client"
    wait_for "login page renders" 60 sh -c "curl -fsS '$BASE_URL/login' | grep -q 'data-page'"
}

# The mode php-fpm actually runs in, read from the `selfHosted` Inertia prop
# of the login page: "selling", "self-hosted", or "unknown".
served_mode() {
    curl -fsS "$BASE_URL/login" 2>/dev/null | python3 -I -c '
import json, re, sys
match = re.search(r"<script data-page=\"app\"[^>]*>(.*?)</script>", sys.stdin.read(), re.S)
if match is None:
    print("unknown"); sys.exit()
props = json.loads(match.group(1)).get("props", {})
print({False: "selling", True: "self-hosted"}.get(props.get("selfHosted"), "unknown"))
'
}

# One-off command in the app image as `app` (UID 1001): never as root, never
# through `exec`, so nothing in the checkout ends up owned by root.
app_run() {
    compose run --rm -T -u app app "$@"
}
