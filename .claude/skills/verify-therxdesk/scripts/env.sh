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
PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v1.61.1-noble"
NODE_MODULES_VOLUME="therxdesk_app-node-modules"

# Evidence lives outside the repo and outside the containers, so cleanup
# never touches it.
EVIDENCE_ROOT="${VERIFY_EVIDENCE_ROOT:-$HOME/.cache/verify-therxdesk/runs}"
# What launch.sh started, so cleanup.sh stops only that.
STATE_DIR="$HOME/.cache/verify-therxdesk/state"

compose() {
    docker compose --progress quiet --project-directory "$MAIN_CHECKOUT" "$@"
}

# One-off command in the app image as `app` (UID 1001): never as root, never
# through `exec`, so nothing in the checkout ends up owned by root.
app_run() {
    compose run --rm -T -u app app "$@"
}
