#!/usr/bin/env bash
# Runs one journey (a key of JOURNEYS in drive.ts) in the Playwright image against the
# dev app, signed in as the fixture Owner, and writes its evidence to a new
# folder. Prints the folder; exits non-zero when the journey fails.
#
#   drive.sh <journey> [evidence folder]
#   drive.sh sign-in
#   drive.sh compose-draft
#   VERIFY_BLOCKED_POST=<id> drive.sh gate-retry
source "$(dirname "$0")/env.sh"

journey="${1:?usage: drive.sh <journey> [evidence folder]}"

out="${2:-$EVIDENCE_ROOT/$(date -u +%Y%m%dT%H%M%SZ)-$journey}"
mkdir -p "$out"

fixture="$("$(dirname "$0")/fixture.sh" show)"

# Only a journey that pays on Stripe needs the card; pass it when set so the
# default stays the 4242 card in drive.ts.
card_env=()

if [ -n "${VERIFY_TEST_CARD:-}" ]; then
    card_env+=(-e "VERIFY_TEST_CARD=$VERIFY_TEST_CARD")
fi

# The welcome-offer journey reads its two link Offers from offer.sh up.
offer_env=()

if [ -s "$STATE_DIR/offers.env" ]; then
    # shellcheck disable=SC1090
    source "$STATE_DIR/offers.env"
    [ -n "${VERIFY_OFFER_BANNER:-}" ] && offer_env+=(-e "VERIFY_OFFER_BANNER=$VERIFY_OFFER_BANNER")
    [ -n "${VERIFY_OFFER_NO_BANNER:-}" ] && offer_env+=(-e "VERIFY_OFFER_NO_BANNER=$VERIFY_OFFER_NO_BANNER")
fi

# The complimentary journeys read the Plan name and Account id from
# complimentary.sh up.
complimentary_env=()

if [ -s "$STATE_DIR/complimentary.env" ]; then
    # shellcheck disable=SC1090
    source "$STATE_DIR/complimentary.env"
    [ -n "${VERIFY_COMPLIMENTARY_PLAN:-}" ] && complimentary_env+=(-e "VERIFY_COMPLIMENTARY_PLAN=$VERIFY_COMPLIMENTARY_PLAN")
    [ -n "${VERIFY_COMPLIMENTARY_ACCOUNT:-}" ] && complimentary_env+=(-e "VERIFY_COMPLIMENTARY_ACCOUNT=$VERIFY_COMPLIMENTARY_ACCOUNT")
fi

# The gate journeys read the post id gate-schedule found (gate-retry) and how
# far ahead gate-schedule schedules (default 5 minutes in drive.ts).
gate_env=()
[ -n "${VERIFY_BLOCKED_POST:-}" ] && gate_env+=(-e "VERIFY_BLOCKED_POST=$VERIFY_BLOCKED_POST")
[ -n "${VERIFY_GATE_LEAD_MINUTES:-}" ] && gate_env+=(-e "VERIFY_GATE_LEAD_MINUTES=$VERIFY_GATE_LEAD_MINUTES")

# The services journey reads a valid Invite id for the closed-signups check.
invite_env=()
[ -n "${VERIFY_INVITE_ID:-}" ] && invite_env+=(-e "VERIFY_INVITE_ID=$VERIFY_INVITE_ID")

# The review-access journey schedules this many days ahead (7 in drive.ts).
review_env=()
[ -n "${VERIFY_REVIEW_LEAD_DAYS:-}" ] && review_env+=(-e "VERIFY_REVIEW_LEAD_DAYS=$VERIFY_REVIEW_LEAD_DAYS")

# The repo is mounted read-only; only the evidence folder is writable. The
# container runs as the host user so the evidence stays the host user's.
docker run --rm --network host --ipc=host \
    -u "$(id -u):$(id -g)" -e HOME=/tmp \
    -e VERIFY_BASE_URL="$BASE_URL" \
    -e VERIFY_FIXTURE="$fixture" \
    -e VERIFY_GUEST_EMAIL_LIKE="$GUEST_EMAIL_LIKE" \
    -e VERIFY_MAILPIT_URL="$MAILPIT_URL" \
    "${card_env[@]}" \
    "${offer_env[@]}" \
    "${complimentary_env[@]}" \
    "${gate_env[@]}" \
    "${invite_env[@]}" \
    "${review_env[@]}" \
    -v "$HERE_TOPLEVEL":/work:ro \
    -v "$NODE_MODULES_VOLUME":/work/node_modules:ro \
    -v "$out":/out \
    -w /work \
    "$PLAYWRIGHT_IMAGE" \
    node "$SKILL_IN_WORK/scripts/drive.ts" "$journey" /out && status=0 || status=$?

echo "evidence: $out"
exit "$status"
