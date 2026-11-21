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

# The repo is mounted read-only; only the evidence folder is writable. The
# container runs as the host user so the evidence stays the host user's.
docker run --rm --network host --ipc=host \
    -u "$(id -u):$(id -g)" -e HOME=/tmp \
    -e VERIFY_BASE_URL="$BASE_URL" \
    -e VERIFY_FIXTURE="$fixture" \
    -e VERIFY_GUEST_EMAIL_LIKE="$GUEST_EMAIL_LIKE" \
    -v "$HERE_TOPLEVEL":/work:ro \
    -v "$NODE_MODULES_VOLUME":/work/node_modules:ro \
    -v "$out":/out \
    -w /work \
    "$PLAYWRIGHT_IMAGE" \
    node "$SKILL_IN_WORK/scripts/drive.ts" "$journey" /out && status=0 || status=$?

echo "evidence: $out"
exit "$status"
