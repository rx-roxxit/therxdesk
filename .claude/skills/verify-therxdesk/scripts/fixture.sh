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
#   fixture.sh lifecycle <hours>      put the fixture on the Lapsed path with a deletion clock <hours> from now
#   fixture.sh token                  print ONLY a fresh personal API token (api.sh captures it)
#
# The fixture is an Owner, their Workspace with one made-up LinkedIn channel,
# and a Member on the same Account (memberEmail in `show`).
source "$(dirname "$0")/env.sh"

if [ "$#" -eq 0 ]; then
    set -- show
fi

# A token is a secret: never print it to a terminal. api.sh captures it into a
# mode-600 curl config, so its stdout is a pipe, never a tty.
if [ "$1" = "token" ] && [ -t 1 ]; then
    echo "[fixture] the API token is a secret and is never printed: use api.sh, which captures it" >&2
    exit 1
fi

compose run --rm -T -u app -e "VERIFY_GUEST_EMAIL_LIKE=$GUEST_EMAIL_LIKE" app php "$SKILL_IN_APP/scripts/fixture.php" "$@"
