#!/usr/bin/env bash
# Drives the lifecycle emails and the stored deletion clock on the fixture
# Account, without touching any other Account.
#
#   lifecycle.sh backdate [hours]   put the fixture on the Lapsed path with a
#                                   deletion clock <hours> from now (default 168 = 7 days)
#   lifecycle.sh sweep              run rxdesk:lifecycle for the fixture, then flush the queue
#   lifecycle.sh emails             list the lifecycle emails in Mailpit
#   lifecycle.sh export             rxdesk:export --dry-run for the fixture
#   lifecycle.sh delete-dry         rxdesk:delete --dry-run for the fixture
#   lifecycle.sh delete             rxdesk:delete for the fixture (data only)
#   lifecycle.sh show               the fixture Account's access_state
#   lifecycle.sh down               fixture.sh up is the reset; nothing to remove here
#
# The fixture is the only Account acted on: every command passes --account.
source "$(dirname "$0")/env.sh"

DIR="$(cd "$(dirname "$0")" && pwd)"
FIXTURE="$DIR/fixture.sh"
MAIL="$DIR/mail.sh"
INTERNAL="$DIR/internal.sh"
OWNER_EMAIL="verify-agent@example.com"

require_selling() {
    if [ ! -f "$SELLING_MARKER" ]; then
        echo "[lifecycle] run selling.sh up first (no selling marker)" >&2
        return 1
    fi
}

fixture_account() {
    "$FIXTURE" show | python3 -I -c '
import json, sys
print(json.load(sys.stdin)["accountId"])
'
}

backdate() {
    require_selling || exit 1
    "$FIXTURE" lifecycle "${1:-168}"
}

sweep() {
    require_selling || exit 1

    local account_id
    account_id="$(fixture_account)" || { echo "[lifecycle] no fixture: run launch.sh" >&2; exit 1; }

    app_run php artisan rxdesk:lifecycle --account="${account_id}" || return 1

    # Mailables are queued; the dev stack has no worker, so flush them.
    app_run php artisan queue:work --stop-when-empty --tries=1 --timeout=60 || true
}

emails() {
    "$MAIL" list --to "$OWNER_EMAIL"
    "$MAIL" list --to verify-member@example.com
}

export_dry() {
    require_selling || exit 1
    local account_id
    account_id="$(fixture_account)" || exit 1
    app_run php artisan rxdesk:export --account="${account_id}" --dry-run
}

delete_dry() {
    require_selling || exit 1
    local account_id
    account_id="$(fixture_account)" || exit 1
    app_run php artisan rxdesk:delete --account="${account_id}" --dry-run
}

delete_now() {
    require_selling || exit 1
    local account_id
    account_id="$(fixture_account)" || exit 1
    app_run php artisan rxdesk:delete --account="${account_id}"
}

show() {
    require_selling || exit 1

    local account_id
    account_id="$(fixture_account)" || exit 1

    "$INTERNAL" GET "/accounts/${account_id}" --token admin | tail -n +2 | python3 -I -c '
import json, sys
print(json.dumps(json.load(sys.stdin).get("access_state"), indent=2))
'
}

case "${1:-show}" in
    backdate) shift || true; backdate "$@" ;;
    sweep) sweep ;;
    emails) emails ;;
    export) export_dry ;;
    delete-dry) delete_dry ;;
    delete) delete_now ;;
    show) show ;;
    down) echo "[lifecycle] nothing to remove: fixture.sh up resets the Account" ;;
    *) echo "usage: lifecycle.sh backdate [hours]|sweep|emails|export|delete-dry|delete|show|down" >&2; exit 1 ;;
esac
