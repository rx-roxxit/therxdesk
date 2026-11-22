#!/usr/bin/env bash
# Turns the fixture Account off through the app's own /internal/v1 API (the way
# the System admin does), lifts it, and shows its access state.
#
#   suspension.sh up [reason]   suspend the fixture Account, write the journey env
#   suspension.sh lift          lift it and remove the journey env
#   suspension.sh show          print the fixture Account's access_state
#   suspension.sh down          lift it if it is still suspended, remove the env
#
# The reason is internal only (the audit log), never shown to a customer. The
# app never prints a token: every call goes through internal.sh.
source "$(dirname "$0")/env.sh"

INTERNAL="$(dirname "$0")/internal.sh"
FIXTURE="$(dirname "$0")/fixture.sh"
SUSPENSION_ENV="$STATE_DIR/suspension.env"
DEFAULT_REASON="Verification run: Suspended screen and publishing gate."

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

reason_body() {
    python3 -I -c 'import json, sys; print(json.dumps({"reason": sys.argv[1]}))' "$1"
}

fixture_account() {
    "$FIXTURE" show | json_field 'accountId'
}

require_selling() {
    if [ ! -f "$SELLING_MARKER" ]; then
        echo "[suspension] run selling.sh up first (no selling marker)" >&2
        return 1
    fi
}

# The journey's Account: from the state file when present, else the fixture's.
journey_account() {
    if [ -s "$SUSPENSION_ENV" ]; then
        # shellcheck disable=SC1090
        source "$SUSPENSION_ENV"
        printf '%s' "${VERIFY_SUSPENSION_ACCOUNT:-}"
        return
    fi

    fixture_account
}

up() {
    require_selling || exit 1

    local reason="${1:-$DEFAULT_REASON}"

    if [ -z "$(printf '%s' "$reason" | tr -d '[:space:]')" ]; then
        echo "[suspension] a reason is required (it is stored in the audit log)" >&2
        exit 1
    fi

    local account_id response status
    account_id="$(fixture_account)" || { echo "[suspension] no fixture: run launch.sh" >&2; exit 1; }
    [ -n "$account_id" ] || { echo "[suspension] the fixture has no account id" >&2; exit 1; }

    response="$("$INTERNAL" POST "/accounts/${account_id}/suspension" --token admin --body "$(reason_body "$reason")")" || return 1
    status="$(printf '%s\n' "$response" | head -1)"

    case "$status" in
        'HTTP 200') ;;
        *) echo "[suspension] FAILED suspending the Account: ${status}" >&2; printf '%s\n' "$response" >&2; return 1 ;;
    esac

    mkdir -p "$STATE_DIR"
    (umask 077 && printf 'VERIFY_SUSPENSION_ACCOUNT=%s\n' "$account_id" >"$SUSPENSION_ENV")
    echo "[suspension] fixture Account ${account_id} is suspended"
}

lift() {
    require_selling || return 1

    local account_id response status
    account_id="$(journey_account)"

    if [ -z "$account_id" ]; then
        echo "[suspension] no fixture Account to lift" >&2
        return 1
    fi

    response="$("$INTERNAL" POST "/accounts/${account_id}/suspension/lift" --token admin)" || return 1
    status="$(printf '%s\n' "$response" | head -1)"

    case "$status" in
        'HTTP 200') echo "[suspension] fixture Account ${account_id} is no longer suspended" ;;
        'HTTP 422') echo "[suspension] fixture Account ${account_id} was not suspended" ;;
        *) echo "[suspension] FAILED lifting the Suspension: ${status}" >&2; printf '%s\n' "$response" >&2; return 1 ;;
    esac

    rm -f "$SUSPENSION_ENV"
}

show() {
    require_selling || exit 1

    local account_id response
    account_id="$(journey_account)"

    if [ -z "$account_id" ]; then
        echo "[suspension] no fixture Account: run launch.sh" >&2
        exit 1
    fi

    response="$("$INTERNAL" GET "/accounts/${account_id}" --token admin)" || return 1
    printf '%s\n' "$response" | tail -n +2 | python3 -I -c '
import json, sys
print(json.dumps(json.load(sys.stdin).get("access_state"), indent=2))
'
}

down() {
    if [ ! -s "$SUSPENSION_ENV" ]; then
        echo "[suspension] no journey env to remove"
        return 0
    fi

    lift || echo "[suspension] the Suspension could not be lifted (the Account may be gone)"
    rm -f "$SUSPENSION_ENV"
    echo "[suspension] journey env removed"
}

case "${1:-show}" in
    up) shift || true; up "$@" ;;
    lift) lift ;;
    show) show ;;
    down) down ;;
    *) echo "usage: suspension.sh up [reason]|lift|show|down" >&2; exit 1 ;;
esac
