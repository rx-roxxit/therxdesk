#!/usr/bin/env bash
# Brings the dev stack up (if it is not already) and waits until it is usable:
# the app container healthy, /up answering, the Vite dev server serving, the
# login page rendering. Then creates the verification Owner (fixture.sh up).
#
#   launch.sh            # start or reuse the stack, then seed the fixture
#
# If the stack was already running, it is the owner's dev instance: launch
# reuses it and records that it did not start it, so cleanup leaves it up.
source "$(dirname "$0")/env.sh"

mkdir -p "$STATE_DIR"

if [ "$(compose ps --status running -q app)" = "" ]; then
    echo "[launch] starting the dev stack (first boot runs composer install, npm ci and migrations: minutes)"
    compose up -d
    touch "$STATE_DIR/started-stack"
else
    echo "[launch] dev stack already running: reusing it, cleanup will leave it up"
    rm -f "$STATE_DIR/started-stack"
fi

wait_until_usable

"$(dirname "$0")/fixture.sh" up
echo "[launch] usable: sign in at $BASE_URL/login with the fixture above"
