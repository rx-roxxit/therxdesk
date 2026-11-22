#!/usr/bin/env bash
# Tears down what a verification run made, and only that:
#   - the fixture Owner and everything it owns (fixture.sh down), its sandbox Customer included
#   - the Owners the register journey signed up (fixture.sh guests)
#   - selling mode, if selling.sh up was run (selling.sh down)
#   - the dev stack, but only if launch.sh started it (state file), then hands
#     the files the root-run entrypoint wrote back to UID 1001
# Never removes evidence ($EVIDENCE_ROOT) and never kills by process name.
source "$(dirname "$0")/env.sh"

# Lifts a Suspension and removes its journey env; a missing state is a no-op.
"$(dirname "$0")/suspension.sh" down

"$(dirname "$0")/fixture.sh" down
"$(dirname "$0")/fixture.sh" guests

# The trial/payment paths leave a Stripe test clock (and its Customer, which
# fixture.sh down deletes); a missing state is a no-op. Never stop the rest of
# the cleanup when Stripe refuses.
"$(dirname "$0")/stripe-clock.sh" delete || echo "[cleanup] the test clock could not be deleted"

# The journey env for the sandbox Offers (Offers themselves are never deleted).
"$(dirname "$0")/offer.sh" down

# Ends the fixture's Complimentary Plan and removes the journey env.
"$(dirname "$0")/complimentary.sh" down

# Back to .env's mode: stop the Stripe listener, recreate app without the overlay.
"$(dirname "$0")/selling.sh" down

# Back to .env's media disk, if media.sh up was run.
"$(dirname "$0")/media.sh" down

if [ -f "$STATE_DIR/started-stack" ]; then
    echo "[cleanup] stopping the dev stack launch.sh started"
    compose stop
    compose run --rm --no-deps app chown -R 1001:1001 lang resources/js public storage bootstrap/cache vendor node_modules .env
    rm -f "$STATE_DIR/started-stack"
else
    echo "[cleanup] the dev stack was already running before launch: leaving it up"
fi

echo "[cleanup] evidence kept in $EVIDENCE_ROOT"
