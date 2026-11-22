#!/usr/bin/env bash
# Gives the fixture Account a Complimentary Plan through the app's own
# /internal/v1 API (the way the System admin does), ends it, and shows it.
#
#   complimentary.sh up     give the fixture Standard, write the journey env
#   complimentary.sh end    end it 1 minute ago, so the Account is Lapsed at once
#   complimentary.sh show   print the fixture Account's complimentary block
#   complimentary.sh down   end it if the journey env exists, then remove the env
#
# The app never prints a token: every call goes through internal.sh.
source "$(dirname "$0")/env.sh"

INTERNAL="$(dirname "$0")/internal.sh"
FIXTURE="$(dirname "$0")/fixture.sh"
COMPLIMENTARY_ENV="$STATE_DIR/complimentary.env"

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

standard_plan() {
    local listing
    listing="$("$INTERNAL" GET '/plans?search=standard' --token admin)" || return 1
    printf '%s\n' "$listing" | tail -n +2
}

up() {
    if [ ! -f "$SELLING_MARKER" ]; then
        echo "[complimentary] run selling.sh up first (no selling marker)" >&2
        exit 1
    fi

    local account_id listing plan_id plan_name response status
    account_id="$(fixture_account)" || { echo "[complimentary] no fixture: run launch.sh" >&2; exit 1; }
    [ -n "$account_id" ] || { echo "[complimentary] the fixture has no account id" >&2; exit 1; }

    listing="$(standard_plan)" || { echo "[complimentary] could not read the Standard Plan" >&2; exit 1; }
    plan_id="$(printf '%s\n' "$listing" | json_field 'data.0.id')"
    plan_name="$(printf '%s\n' "$listing" | json_field 'data.0.name')"
    [ -n "$plan_id" ] || { echo "[complimentary] the Standard Plan has no id" >&2; exit 1; }

    response="$("$INTERNAL" POST "/accounts/${account_id}/complimentary" --token admin --body "{
        \"plan_id\": \"${plan_id}\"
    }")" || return 1
    status="$(printf '%s\n' "$response" | head -1)"
    case "$status" in
        'HTTP 200'|'HTTP 201') ;;
        *) echo "[complimentary] FAILED giving the Plan: ${status}" >&2; printf '%s\n' "$response" >&2; return 1 ;;
    esac

    mkdir -p "$STATE_DIR"
    (umask 077 && printf 'VERIFY_COMPLIMENTARY_PLAN=%s\nVERIFY_COMPLIMENTARY_ACCOUNT=%s\n' "$plan_name" "$account_id" >"$COMPLIMENTARY_ENV")
    echo "[complimentary] fixture Account ${account_id} now has ${plan_name}"
}

end() {
    if [ ! -s "$COMPLIMENTARY_ENV" ]; then
        echo "[complimentary] run complimentary.sh up first (no journey env)" >&2
        exit 1
    fi

    # shellcheck disable=SC1090
    source "$COMPLIMENTARY_ENV"

    local response status ends_at
    ends_at="$(date -u -d '1 minute ago' +%Y-%m-%dT%H:%M:%SZ)"
    response="$("$INTERNAL" POST "/accounts/${VERIFY_COMPLIMENTARY_ACCOUNT}/complimentary/end" --token admin --body "{
        \"ends_at\": \"${ends_at}\"
    }")" || return 1
    status="$(printf '%s\n' "$response" | head -1)"
    echo "[complimentary] end: ${status}"
}

show() {
    if [ ! -s "$COMPLIMENTARY_ENV" ]; then
        echo "[complimentary] run complimentary.sh up first (no journey env)" >&2
        exit 1
    fi

    # shellcheck disable=SC1090
    source "$COMPLIMENTARY_ENV"

    local response
    response="$("$INTERNAL" GET "/accounts/${VERIFY_COMPLIMENTARY_ACCOUNT}" --token admin)" || return 1
    printf '%s\n' "$response" | tail -n +2 | python3 -I -c '
import json, sys
print(json.dumps(json.load(sys.stdin).get("complimentary"), indent=2))
'
}

down() {
    if [ ! -s "$COMPLIMENTARY_ENV" ]; then
        echo "[complimentary] no journey env to remove"
        return 0
    fi

    end || echo "[complimentary] the Plan could not be ended (the Account may be gone)"
    rm -f "$COMPLIMENTARY_ENV"
    echo "[complimentary] journey env removed"
}

case "${1:-show}" in
    up) up ;;
    end) end ;;
    show) show ;;
    down) down ;;
    *) echo "usage: complimentary.sh up|end|show|down" >&2; exit 1 ;;
esac
