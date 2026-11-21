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

wait_for() {
    local what="$1" seconds="$2"
    shift 2
    local waited=0
    until "$@" >/dev/null 2>&1; do
        waited=$((waited + 2))
        if [ "$waited" -ge "$seconds" ]; then
            echo "[launch] FAILED: $what not ready after ${seconds}s" >&2
            compose logs --tail=40 app >&2
            exit 1
        fi
        sleep 2
    done
    echo "[launch] ready: $what"
}

app_healthy() {
    [ "$(docker inspect -f '{{.State.Health.Status}}' "$(compose ps -q app)")" = "healthy" ]
}

wait_for "app container healthy (entrypoint done)" 900 app_healthy
wait_for "GET $BASE_URL/up" 120 curl -fsS "$BASE_URL/up"
wait_for "Vite dev server at $VITE_URL" 120 curl -fsS "$VITE_URL/@vite/client"
wait_for "login page renders" 60 sh -c "curl -fsS '$BASE_URL/login' | grep -q 'data-page'"

"$(dirname "$0")/fixture.sh" up
echo "[launch] usable: sign in at $BASE_URL/login with the fixture above"
