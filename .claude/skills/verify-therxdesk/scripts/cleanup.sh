#!/usr/bin/env bash
# Tears down what a verification run made, and only that:
#   - the fixture Owner and everything it owns (fixture.sh down), its sandbox Customer included
#   - selling mode, if selling.sh up was run (selling.sh down)
#   - the dev stack, but only if launch.sh started it (state file), then hands
#     the files the root-run entrypoint wrote back to UID 1001
# Never removes evidence ($EVIDENCE_ROOT) and never kills by process name.
source "$(dirname "$0")/env.sh"

"$(dirname "$0")/fixture.sh" down

# Back to .env's mode: stop the Stripe listener, recreate app without the overlay.
"$(dirname "$0")/selling.sh" down

if [ -f "$STATE_DIR/started-stack" ]; then
    echo "[cleanup] stopping the dev stack launch.sh started"
    compose stop
    compose run --rm --no-deps app chown -R 1001:1001 lang resources/js public storage bootstrap/cache vendor node_modules .env
    rm -f "$STATE_DIR/started-stack"
else
    echo "[cleanup] the dev stack was already running before launch: leaving it up"
fi

echo "[cleanup] evidence kept in $EVIDENCE_ROOT"
