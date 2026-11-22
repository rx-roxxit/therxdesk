#!/usr/bin/env bash
# Calls the app's PUBLIC API (/api) as the fixture Owner, using a personal API
# token issued on the fly. This is the only way to prove what an API client
# sees while the Account cannot use the app (a Suspension answers 402).
#
#   api.sh GET /posts
#   api.sh GET /posts/<id>
#
# Prints "HTTP <status>" and the pretty-printed body; exit 0 whenever a
# response arrived. The token never reaches the terminal or curl's argv:
# fixture.php prints it only into this script, which keeps it in a mode-600
# curl config.
source "$(dirname "$0")/env.sh"

METHOD="GET"
PATH_ARG=""
BODY=""
CFG=""
TMP_BODY=""
DATA_FILE=""

while [ "$#" -gt 0 ]; do
    case "$1" in
        GET|POST|PUT|PATCH|DELETE)
            METHOD="$1"
            shift
            ;;
        --body)
            BODY="${2:-}"
            shift 2
            ;;
        *)
            if [ -z "$PATH_ARG" ]; then
                PATH_ARG="$1"
                shift
            else
                echo "unexpected arg: $1" >&2
                exit 1
            fi
            ;;
    esac
done

if [ -z "$PATH_ARG" ]; then
    echo "usage: api.sh <GET|POST|PUT|PATCH|DELETE> <path> [--body <json>]" >&2
    exit 1
fi

case "$PATH_ARG" in
    /api/*) ;;
    /*) PATH_ARG="/api$PATH_ARG" ;;
    *) PATH_ARG="/api/$PATH_ARG" ;;
esac

# Issue the token inside the app image and capture it; the tty guard in
# fixture.sh stops anyone printing it by hand.
TOKEN="$("$(dirname "$0")/fixture.sh" token | tail -n1)" || {
    echo "could not issue an API token" >&2
    exit 1
}

TOKEN="$(printf '%s' "$TOKEN" | tr -d '\r\n')"

if [ -z "$TOKEN" ]; then
    echo "the API token came back empty" >&2
    exit 1
fi

case "$TOKEN" in
    *[[:space:]]*)
        echo "the API token has an unexpected shape" >&2
        exit 1
        ;;
esac

CFG="$(mktemp)"
chmod 600 "$CFG"
{
    printf 'header = "Accept: application/json"\n'
    printf 'header = "Authorization: Bearer %s"\n' "$TOKEN"
} >"$CFG"

TMP_BODY="$(mktemp)"
CURL_ARGS=(-sS -o "$TMP_BODY" -w '%{http_code}' -K "$CFG" -X "$METHOD")

if [ -n "$BODY" ]; then
    DATA_FILE="$(mktemp)"
    chmod 600 "$DATA_FILE"
    printf '%s' "$BODY" >"$DATA_FILE"
    CURL_ARGS+=(-H 'Content-Type: application/json' --data-binary "@$DATA_FILE")
fi

cleanup() {
    rm -f "$CFG" "$TMP_BODY"

    if [ -n "$DATA_FILE" ]; then
        rm -f "$DATA_FILE"
    fi
}
trap cleanup EXIT

HTTP_CODE="$(curl "${CURL_ARGS[@]}" "$BASE_URL$PATH_ARG")" || {
    echo "curl failed" >&2
    exit 1
}

echo "HTTP $HTTP_CODE"

if [ -s "$TMP_BODY" ]; then
    if python3 -I -m json.tool <"$TMP_BODY" 2>/dev/null; then
        :
    else
        cat "$TMP_BODY"
        echo
    fi
fi
