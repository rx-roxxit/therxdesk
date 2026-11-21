#!/usr/bin/env bash
# The verification Owner on the dev database (see fixture.php).
#
#   fixture.sh up     replace it with a fresh one and print it as JSON
#   fixture.sh show   print it as JSON (read-only); exit 1 when there is none
#   fixture.sh down   remove it and everything it owns
source "$(dirname "$0")/env.sh"

app_run php "$SKILL_IN_APP/scripts/fixture.php" "${1:-show}"
