#!/usr/bin/env bash
# Creates the two sandbox link Offers the welcome-offer journey drives, through
# the app's own /internal/v1 API (the app creates each Stripe coupon itself).
# Both cover Mini monthly with 20% off the first 3 months and a 14-day trial;
# one shows the banner and one does not. The fixed slugs and the stable coupon
# ids make a second run reuse them instead of creating more.
#
#   offer.sh up     find or create both Offers, write the journey env
#   offer.sh show   print the journey env
#   offer.sh down   remove the journey env (Offers are never deleted)
source "$(dirname "$0")/env.sh"

INTERNAL="$(dirname "$0")/internal.sh"
OFFERS_ENV="$STATE_DIR/offers.env"
BANNER_SLUG="verify-offer-banner"
NO_BANNER_SLUG="verify-offer-nobanner"

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

mini_id() {
    local listing
    listing="$("$INTERNAL" GET '/plans?search=mini' --token admin)" || return 1
    printf '%s\n' "$listing" | tail -n +2 | json_field 'data.0.id'
}

offer_exists() {
    local slug="$1" listing
    listing="$("$INTERNAL" GET "/offers?search=${slug}" --token admin)" || return 1
    printf '%s\n' "$listing" | tail -n +2 | python3 -I -c '
import json, sys
slug = sys.argv[1]
data = json.load(sys.stdin).get("data", [])
print("yes" if any(row.get("slug") == slug for row in data) else "no")
' "$slug"
}

create_offer() {
    local slug="$1" name="$2" banner="$3" plan_id="$4" starts_at="$5" response status
    response="$("$INTERNAL" POST /offers --token admin --body "{
        \"slug\": \"${slug}\",
        \"name\": \"${name}\",
        \"reach\": \"link\",
        \"coupon\": {
            \"type\": \"percent\",
            \"percent_off\": 20,
            \"duration\": \"repeating\",
            \"duration_in_months\": 3
        },
        \"trial_days\": 14,
        \"starts_at\": \"${starts_at}\",
        \"show_banner\": ${banner},
        \"plan_ids\": [\"${plan_id}\"],
        \"intervals\": [\"monthly\"]
    }")" || return 1
    status="$(printf '%s\n' "$response" | head -1)"
    case "$status" in
        'HTTP 201'|'HTTP 200') ;;
        *) echo "[offer] FAILED creating ${slug}: ${status}" >&2; printf '%s\n' "$response" >&2; return 1 ;;
    esac
}

up() {
    if [ ! -f "$SELLING_MARKER" ]; then
        echo "[offer] run selling.sh up first (no selling marker)" >&2
        exit 1
    fi

    local plan_id starts_at
    plan_id="$(mini_id)" || { echo "[offer] could not read the Mini Plan" >&2; exit 1; }
    [ -n "$plan_id" ] || { echo "[offer] the Mini Plan has no id" >&2; exit 1; }
    starts_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

    if [ "$(offer_exists "$BANNER_SLUG")" = "no" ]; then
        create_offer "$BANNER_SLUG" "Verify banner" true "$plan_id" "$starts_at"
    fi

    if [ "$(offer_exists "$NO_BANNER_SLUG")" = "no" ]; then
        create_offer "$NO_BANNER_SLUG" "Verify no banner" false "$plan_id" "$starts_at"
    fi

    mkdir -p "$STATE_DIR"
    (umask 077 && printf 'VERIFY_OFFER_BANNER=%s\nVERIFY_OFFER_NO_BANNER=%s\n' "$BANNER_SLUG" "$NO_BANNER_SLUG" >"$OFFERS_ENV")
    echo "[offer] journey Offers ready: ${BANNER_SLUG}, ${NO_BANNER_SLUG}"
}

show() {
    if [ ! -s "$OFFERS_ENV" ]; then
        echo "run offer.sh up first (no offer env)" >&2
        exit 1
    fi
    cat "$OFFERS_ENV"
}

down() {
    rm -f "$OFFERS_ENV"
    echo "[offer] journey env removed; the sandbox Offers stay (Offers are never deleted)"
}

case "${1:-show}" in
    up) up ;;
    show) show ;;
    down) down ;;
    *) echo "usage: offer.sh up|show|down" >&2; exit 1 ;;
esac
