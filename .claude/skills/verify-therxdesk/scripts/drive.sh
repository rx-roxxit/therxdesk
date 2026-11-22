#!/usr/bin/env bash
# Runs one journey (a key of JOURNEYS in drive.ts) in the Playwright image against the
# dev app, signed in as the fixture Owner, and writes its evidence to a new
# folder. Prints the folder; exits non-zero when the journey fails.
#
#   drive.sh <journey> [evidence folder]
#   drive.sh sign-in
#   drive.sh compose-draft
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

# The repo is mounted read-only; only the evidence folder is writable. The
# container runs as the host user so the evidence stays the host user's.
docker run --rm --network host --ipc=host \
    -u "$(id -u):$(id -g)" -e HOME=/tmp \
    -e VERIFY_BASE_URL="$BASE_URL" \
    -e VERIFY_FIXTURE="$fixture" \
    -e VERIFY_GUEST_EMAIL_LIKE="$GUEST_EMAIL_LIKE" \
    -e VERIFY_MAILPIT_URL="$MAILPIT_URL" \
    "${card_env[@]}" \
    -v "$HERE_TOPLEVEL":/work:ro \
    -v "$NODE_MODULES_VOLUME":/work/node_modules:ro \
    -v "$out":/out \
    -w /work \
    "$PLAYWRIGHT_IMAGE" \
    node "$SKILL_IN_WORK/scripts/drive.ts" "$journey" /out && status=0 || status=$?

echo "evidence: $out"
exit "$status"
