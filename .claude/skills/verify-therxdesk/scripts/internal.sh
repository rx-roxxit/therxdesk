#!/usr/bin/env bash
# Calls the main app's /internal/v1 with a token from the selling-mode state
# file. The token never reaches the terminal, the logs or curl's argv.
#
#   internal.sh GET /site --token site
#   internal.sh GET /accounts --token admin --email verify-admin@example.com
#   internal.sh GET '/accounts?search=ann' --token admin
#
# --token admin|site|none|bogus (default admin). --email sets the admin
# request's X-Internal-Admin-Email header. --body sends a JSON body. Prints
# "HTTP <status>" and the pretty-printed body; exit 0 whenever a response
# arrived.
source "$(dirname "$0")/env.sh"

METHOD="GET"
PATH_ARG=""
TOKEN="admin"
EMAIL="verify-admin@example.com"
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
        --token)
            TOKEN="${2:-}"
            shift 2
            ;;
        --email)
            EMAIL="${2:-}"
            shift 2
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
    echo "usage: internal.sh <METHOD> <path> [--token admin|site|none|bogus] [--email <email>] [--body <json>]" >&2
    exit 1
fi

if [ ! -f "$SELLING_MARKER" ]; then
    echo "run selling.sh up first (no selling marker)" >&2
    exit 1
fi

if [ ! -s "$INTERNAL_SECRET_FILE" ]; then
    echo "run selling.sh up first (no internal token file)" >&2
    exit 1
fi

case "$PATH_ARG" in
    /internal/v1/*) ;;
    /internal/*) PATH_ARG="/internal/v1${PATH_ARG#/internal}" ;;
    /*) PATH_ARG="/internal/v1$PATH_ARG" ;;
    *) PATH_ARG="/internal/v1/$PATH_ARG" ;;
esac

# Read one value without echoing it.
read_token() {
    sed -n "s/^$1=//p" "$INTERNAL_SECRET_FILE"
}

case "$TOKEN" in
    admin) AUTH="Authorization: Bearer $(read_token RXDESK_INTERNAL_ADMIN_TOKEN)" ;;
    site) AUTH="Authorization: Bearer $(read_token RXDESK_INTERNAL_SITE_TOKEN)" ;;
    bogus) AUTH="Authorization: Bearer bogus" ;;
    none) AUTH="" ;;
    *)
        echo "unknown token: $TOKEN" >&2
        exit 1
        ;;
esac

# A mode-600 curl config keeps the token out of curl's argv (and out of `ps`).
CFG="$(mktemp)"
chmod 600 "$CFG"

{
    printf 'header = "Accept: application/json"\n'

    if [ -n "$AUTH" ]; then
        printf 'header = "%s"\n' "$AUTH"
    fi

    if [ "$TOKEN" = "admin" ]; then
        printf 'header = "X-Internal-Admin-Email: %s"\n' "$EMAIL"
    fi
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
