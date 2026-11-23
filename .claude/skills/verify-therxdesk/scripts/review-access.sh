#!/usr/bin/env bash
# Grants or revokes the fixture Account's Review access through the app's own
# /internal/v1 API, the way the System admin does. A reviewer Account may
# connect and publish to networks that are installed but switched off.
#
#   review-access.sh on     enable Review access for the fixture Account
#   review-access.sh off    disable it (the restore step)
#   review-access.sh show   print the Account's review_access value
#
# The app never prints a token: every call goes through internal.sh.
source "$(dirname "$0")/env.sh"

INTERNAL="$(dirname "$0")/internal.sh"
FIXTURE="$(dirname "$0")/fixture.sh"

json_field() {
    python3 -I -c '
import json, sys
data = json.load(sys.stdin)
for key in sys.argv[1].split("."):
    if isinstance(data, list):
        data = data[int(key)]
    else:
        data = data[key]
print(data)
' "$1"
}

fixture_account() {
    "$FIXTURE" show | json_field 'accountId'
}

require_fixture() {
    if [ ! -f "$SELLING_MARKER" ]; then
        echo "[review-access] run selling.sh up first (no selling marker)" >&2
        exit 1
    fi

    account_id="$(fixture_account)" || { echo "[review-access] no fixture: run launch.sh" >&2; exit 1; }
    [ -n "$account_id" ] || { echo "[review-access] the fixture has no account id" >&2; exit 1; }
}

set_access() {
    local enabled="$1" response status value
    require_fixture

    response="$("$INTERNAL" PATCH "/accounts/${account_id}/review-access" --token admin --body "{
        \"enabled\": ${enabled}
    }")" || return 1
    status="$(printf '%s\n' "$response" | head -1)"
    case "$status" in
        'HTTP 200') ;;
        *) echo "[review-access] FAILED: ${status}" >&2; printf '%s\n' "$response" >&2; return 1 ;;
    esac

    value="$(printf '%s\n' "$response" | tail -n +2 | json_field 'review_access')"
    echo "[review-access] fixture Account ${account_id} review_access=${value}"
}

show() {
    local response
    require_fixture

    response="$("$INTERNAL" GET "/accounts/${account_id}" --token admin)" || return 1
    printf '%s\n' "$response" | tail -n +2 | json_field 'review_access'
}

case "${1:-show}" in
    on) set_access true ;;
    off) set_access false ;;
    show) show ;;
    *) echo "usage: review-access.sh on|off|show" >&2; exit 1 ;;
esac
