#!/usr/bin/env bash
# The verification Owner on the dev database (see fixture.php).
#
#   fixture.sh up     replace it with a fresh one and print it as JSON
#   fixture.sh show   print it as JSON (read-only); exit 1 when there is none
#   fixture.sh down   remove it and everything it owns
#   fixture.sh guests remove the Owners the register journey signed up
#   fixture.sh customer <cus_id>      point the fixture Account at a sandbox Customer
#   fixture.sh customer-clear         forget that Customer
#   fixture.sh card <fingerprint>     claim a card fingerprint for the fixture Account
source "$(dirname "$0")/env.sh"

if [ "$#" -eq 0 ]; then
    set -- show
fi

compose run --rm -T -u app -e "VERIFY_GUEST_EMAIL_LIKE=$GUEST_EMAIL_LIKE" app php "$SKILL_IN_APP/scripts/fixture.php" "$@"
