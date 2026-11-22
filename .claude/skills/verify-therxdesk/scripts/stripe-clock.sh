#!/usr/bin/env bash
# A Stripe test clock for the trial and payment paths
# (features/trial-and-payment.md). It moves the sandbox clock so a Trial can
# end, a renewal can fail and Smart Retries can run out, without waiting days.
#
#   stripe-clock.sh create              a fresh clock frozen at now
#   stripe-clock.sh customer [email]    a sandbox Customer on that clock
#   stripe-clock.sh attach              point the fixture Account at that Customer
#   stripe-clock.sh advance <epoch>     move the clock, wait until Stripe is ready
#   stripe-clock.sh status              the clock id, status, frozen time and Customer
#   stripe-clock.sh fingerprint [pm_id] a payment method's card fingerprint
#   stripe-clock.sh delete              delete the clock and its state (no-op when none)
#
# The clock and its Customer live in a mode-600 state file. The file holds ids,
# never a secret; nothing here prints a secret either. stripe.sh is the only
# place the sandbox key is read.
source "$(dirname "$0")/env.sh"

STATE_FILE="$STATE_DIR/stripe-clock.env"
STRIPE="$(dirname "$0")/stripe.sh"
FIXTURE="$(dirname "$0")/fixture.sh"

state_value() {
    sed -n "s/^$1=//p" "$STATE_FILE"
}

write_state() {
    mkdir -p "$STATE_DIR"
    (umask 077 && printf 'CLOCK_ID=%s\nCUSTOMER_ID=%s\n' "$1" "$2" >"$STATE_FILE")
}

read_clock() {
    if [ ! -f "$STATE_FILE" ]; then
        echo "[clock] no clock: run stripe-clock.sh create" >&2
        exit 1
    fi

    local clock
    clock="$(state_value CLOCK_ID)"

    if [ -z "$clock" ]; then
        echo "[clock] $STATE_FILE has no CLOCK_ID: run stripe-clock.sh create" >&2
        exit 1
    fi

    printf '%s\n' "$clock"
}

read_customer() {
    local customer
    customer="$(state_value CUSTOMER_ID 2>/dev/null || true)"

    if [ -z "$customer" ]; then
        echo "[clock] no Customer: run stripe-clock.sh customer" >&2
        exit 1
    fi

    printf '%s\n' "$customer"
}

command_name="${1:-}"

if [ "$#" -gt 0 ]; then
    shift
fi

case "$command_name" in
    create)
        if [ -f "$STATE_FILE" ]; then
            echo "[clock] a clock already exists: run stripe-clock.sh delete first" >&2
            exit 1
        fi

        frozen="$(date +%s)"
        clock="$("$STRIPE" post /v1/test_helpers/test_clocks \
            -d "frozen_time=$frozen" -d name=verify-clock \
            | python3 -I -c 'import json, sys; print(json.load(sys.stdin)["id"])')"

        if [ -z "$clock" ]; then
            echo "[clock] Stripe returned no clock id" >&2
            exit 1
        fi

        write_state "$clock" ""
        echo "[clock] created $clock frozen at $frozen"
        ;;

    customer)
        clock="$(read_clock)"
        email="${1:-verify-agent@example.com}"
        customer="$("$STRIPE" post /v1/customers \
            -d "test_clock=$clock" -d "email=$email" \
            | python3 -I -c 'import json, sys; print(json.load(sys.stdin)["id"])')"

        if [ -z "$customer" ]; then
            echo "[clock] Stripe returned no Customer id" >&2
            exit 1
        fi

        write_state "$clock" "$customer"
        echo "[clock] created $customer on $clock for $email"
        ;;

    attach)
        "$FIXTURE" customer "$(read_customer)"
        ;;

    advance)
        if [ "$#" -lt 1 ]; then
            echo "usage: stripe-clock.sh advance <epoch>" >&2
            exit 2
        fi

        clock="$(read_clock)"
        target="$1"

        "$STRIPE" post "/v1/test_helpers/test_clocks/$clock/advance" \
            -d "frozen_time=$target" >/dev/null

        # Advancing queues Stripe's events; it answers them asynchronously.
        for _ in $(seq 1 90); do
            status="$("$STRIPE" get "/v1/test_helpers/test_clocks/$clock" \
                | python3 -I -c 'import json, sys; print(json.load(sys.stdin)["status"])' 2>/dev/null || true)"

            if [ "$status" = "ready" ]; then
                echo "[clock] ready at $target"
                exit 0
            fi

            sleep 2
        done

        echo "[clock] not ready after 180s (status: ${status:-unknown})" >&2
        exit 1
        ;;

    status)
        clock="$(read_clock)"

        "$STRIPE" get "/v1/test_helpers/test_clocks/$clock" \
            | python3 -I -c '
import datetime
import json
import sys

clock = json.load(sys.stdin)
frozen = clock.get("frozen_time")
readable = None

if isinstance(frozen, int):
    readable = datetime.datetime.fromtimestamp(frozen, datetime.timezone.utc).isoformat()

print("clock:", clock.get("id"))
print("status:", clock.get("status"))
print("frozen_time:", frozen, f"({readable})" if readable else "")
'

        if [ -f "$STATE_FILE" ]; then
            customer="$(state_value CUSTOMER_ID)"

            if [ -n "$customer" ]; then
                echo "customer: $customer"
            fi
        fi
        ;;

    fingerprint)
        method="${1:-pm_card_visa}"

        "$STRIPE" get "/v1/payment_methods/$method" \
            | python3 -I -c '
import json
import sys

method = json.load(sys.stdin)
fingerprint = (method.get("card") or {}).get("fingerprint")

if not fingerprint:
    sys.exit("no card fingerprint on " + str(method.get("id")))

print(fingerprint)
'
        ;;

    delete)
        if [ ! -f "$STATE_FILE" ]; then
            echo "[clock] no clock to delete"
            exit 0
        fi

        clock="$(state_value CLOCK_ID)"

        if [ -n "$clock" ]; then
            if ! "$STRIPE" delete -c "/v1/test_helpers/test_clocks/$clock" >/dev/null 2>&1; then
                echo "[clock] warning: Stripe did not delete $clock (already gone?)" >&2
            fi
        fi

        rm -f "$STATE_FILE"
        echo "[clock] deleted ${clock:-nothing}"
        ;;

    *)
        echo "usage: stripe-clock.sh create|customer [email]|attach|advance <epoch>|status|fingerprint [pm_id]|delete" >&2
        exit 2
        ;;
esac
