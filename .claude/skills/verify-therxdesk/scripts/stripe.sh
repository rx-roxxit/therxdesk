#!/usr/bin/env bash
# Runs one Stripe CLI command against the SANDBOX in the Stripe CLI image
# (nothing is installed on the host). The key is STRIPE_SECRET from the main
# checkout's .env, handed to the container through the environment, never on
# a command line and never printed. Refuses a key that is not a test key.
#
#   stripe.sh get /v1/customers -d email=verify-agent@example.com
#   stripe.sh get /v1/subscriptions -d customer=cus_...
#
# Never run `stripe.sh listen` by hand: it prints the webhook signing secret.
source "$(dirname "$0")/env.sh"

STRIPE_API_KEY="$(grep -m1 '^STRIPE_SECRET=' "$MAIN_CHECKOUT/.env" | cut -d= -f2- | tr -d "\"'")"

case "$STRIPE_API_KEY" in
    sk_test_* | rk_test_*) ;;
    "")
        echo "[stripe] STRIPE_SECRET is empty in .env" >&2
        exit 1
        ;;
    *)
        echo "[stripe] STRIPE_SECRET in .env is not a test-mode key: refusing" >&2
        exit 1
        ;;
esac

export STRIPE_API_KEY
docker run --rm -e STRIPE_API_KEY "$STRIPE_CLI_IMAGE" "$@" 2>/dev/null
