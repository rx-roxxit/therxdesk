#!/usr/bin/env bash
# Switches the running dev stack between self-hosted mode (what .env says) and
# selling mode (SELF_HOSTED=false, Stripe sandbox, webhooks forwarded by the
# Stripe CLI), without editing .env. See compose.selling.yaml.
#
#   selling.sh up       app catalog synced to Stripe, webhook secret, recreate app in selling mode, start the listener
#   selling.sh down     stop the listener, recreate app from .env (self-hosted)
#   selling.sh status   the marker, the app container's SELF_HOSTED, what php-fpm serves, the listener
#   selling.sh logs     the listener's log, with the signing secret redacted
#   selling.sh heartbeat  the local heartbeat receiver's log
#
# Never prints STRIPE_SECRET or the webhook signing secret.
source "$(dirname "$0")/env.sh"

STRIPE="$(dirname "$0")/stripe.sh"

# Seeds the app's own Plan catalog (idempotent, no env) and lets the app
# create every Stripe Product and Price itself: Stripe first, then the
# database, and a second run creates nothing.
sync_catalog() {
    echo "[selling] seeding the app's Plan catalog"
    # The running app may predate these migrations: migrate first, as the
    # entrypoint does on every recreate.
    app_run php artisan migrate --force
    app_run php artisan db:seed --class='Database\Seeders\PlanSeeder' --force

    echo "[selling] syncing the catalog to the sandbox"
    app_run php artisan rxdesk:catalog-sync

    archive_legacy_sandbox_objects
}

# Archives the sandbox Products and Prices that earlier selling.sh runs created
# with metadata rxdesk_verify=true or a rxdesk-verify-* lookup key. Stripe
# Prices are immutable, so they are deactivated (active=false), never deleted,
# and the listing asks for active objects only: a second run finds nothing.
archive_legacy_sandbox_objects() {
    local listing id

    if ! listing="$("$STRIPE" get /v1/products -d active=true -d limit=100)"; then
        echo "[selling] FAILED: could not list the sandbox Products" >&2
        exit 1
    fi

    while IFS= read -r id; do
        [ -n "$id" ] || continue
        "$STRIPE" post "/v1/products/$id" -d active=false >/dev/null
        echo "[selling] archived legacy sandbox product $id"
    done <<< "$(printf '%s' "$listing" | python3 -I -c '
import json, sys
for product in json.load(sys.stdin).get("data", []):
    if str((product.get("metadata") or {}).get("rxdesk_verify")) == "true":
        print(product["id"])
')"

    if ! listing="$("$STRIPE" get /v1/prices -d active=true -d limit=100)"; then
        echo "[selling] FAILED: could not list the sandbox Prices" >&2
        exit 1
    fi

    while IFS= read -r id; do
        [ -n "$id" ] || continue
        "$STRIPE" post "/v1/prices/$id" -d active=false >/dev/null
        echo "[selling] archived legacy sandbox price $id"
    done <<< "$(printf '%s' "$listing" | python3 -I -c '
import json, sys
for price in json.load(sys.stdin).get("data", []):
    metadata = price.get("metadata") or {}
    lookup = price.get("lookup_key") or ""
    if str(metadata.get("rxdesk_verify")) == "true" or lookup.startswith("rxdesk-verify-"):
        print(price["id"])
')"
}

write_webhook_secret() {
    local secret
    secret="$("$STRIPE" listen --print-secret | tr -d '\r\n')"
    [[ "$secret" == whsec_* ]] || { echo "[selling] FAILED: the Stripe CLI gave no webhook signing secret" >&2; exit 1; }

    mkdir -p "$STATE_DIR"
    (umask 077 && printf 'STRIPE_WEBHOOK_SECRET=%s\n' "$secret" >"$SELLING_SECRET_FILE")
    unset secret
    echo "[selling] webhook signing secret written to $SELLING_SECRET_FILE (mode 600, not shown)"
}

write_internal_tokens() {
    if [ -s "$INTERNAL_SECRET_FILE" ]; then
        echo "[selling] internal API tokens already present ($INTERNAL_SECRET_FILE)"
        return
    fi

    mkdir -p "$STATE_DIR"
    local admin site
    admin="$(python3 -I -c 'import secrets;print(secrets.token_hex(32))')"
    site="$(python3 -I -c 'import secrets;print(secrets.token_hex(32))')"
    (umask 077 && printf 'RXDESK_INTERNAL_ADMIN_TOKEN=%s\nRXDESK_INTERNAL_SITE_TOKEN=%s\n' "$admin" "$site" >"$INTERNAL_SECRET_FILE")
    unset admin site
    echo "[selling] internal API tokens written to $INTERNAL_SECRET_FILE (mode 600, not shown)"
}

# grep reads the whole log (no -q): with pipefail, -q closing the pipe early
# would fail the pipeline on SIGPIPE.
listener_ready() {
    compose logs --no-log-prefix stripe 2>/dev/null | grep 'Ready!' >/dev/null
}

served_mode_is() {
    [ "$(served_mode)" = "$1" ]
}

# Whether a service of the selling overlay is running, with or without the
# marker (status runs in both states).
service_running() {
    docker compose --progress quiet --project-directory "$MAIN_CHECKOUT" \
        -f "$MAIN_CHECKOUT/compose.yaml" -f "$MAIN_CHECKOUT/compose.override.yaml" -f "$SELLING_OVERLAY" \
        ps --status running -q "$1" 2>/dev/null || true
}

heartbeat_ready() {
    [ -n "$(service_running heartbeat)" ]
}

up() {
    if [ -z "$(compose ps --status running -q app)" ]; then
        echo "[selling] the dev stack is not running: run launch.sh first" >&2
        exit 1
    fi

    sync_catalog
    write_webhook_secret

    touch "$SELLING_MARKER"
    write_internal_tokens
    echo "[selling] recreating app in selling mode and starting the Stripe listener"
    compose up -d app stripe heartbeat

    wait_until_usable
    wait_for "php-fpm serves selling mode (selfHosted=false)" 60 served_mode_is selling
    wait_for "Stripe listener ready" 60 listener_ready
    wait_for "heartbeat receiver (HTTP 8080)" 60 heartbeat_ready
    echo "[selling] selling mode is up; selling.sh down returns to .env's mode"
}

down() {
    if [ ! -f "$SELLING_MARKER" ]; then
        echo "[selling] not in selling mode (no marker): nothing to do"
        return
    fi

    echo "[selling] stopping the Stripe listener and the heartbeat receiver"
    compose rm -sf stripe heartbeat
    rm -f "$SELLING_MARKER"

    echo "[selling] recreating app from .env"
    compose up -d app
    rm -f "$SELLING_SECRET_FILE"
    rm -f "$INTERNAL_SECRET_FILE"

    wait_until_usable
    echo "[selling] php-fpm serves: $(served_mode)"
}

status() {
    local app_id listener container_mode
    if [ -f "$SELLING_MARKER" ]; then echo "marker    selling ($SELLING_MARKER)"; else echo "marker    none (self-hosted per .env)"; fi
    if [ -f "$INTERNAL_SECRET_FILE" ]; then echo "internal  token file present"; else echo "internal  no token file"; fi

    app_id="$(compose ps --status running -q app 2>/dev/null)"
    if [ -n "$app_id" ]; then
        container_mode="$(docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "$app_id" | sed -n 's/^SELF_HOSTED=//p' | head -1)"
        echo "container SELF_HOSTED=${container_mode:-<not set: .env decides>}"
    else
        echo "container app not running"
    fi

    echo "served    $(served_mode)"

    listener="$(service_running stripe)"
    if [ -n "$listener" ]; then echo "listener  running"; else echo "listener  not running"; fi

    if [ -n "$(service_running heartbeat)" ]; then echo "heartbeat running"; else echo "heartbeat not running"; fi
}

logs() {
    compose logs --no-log-prefix --tail="${1:-200}" stripe | sed -E 's/whsec_[A-Za-z0-9]+/whsec_<redacted>/g'
}

heartbeat_logs() {
    compose logs --no-log-prefix --tail="${1:-200}" heartbeat
}

case "${1:-status}" in
    up) up ;;
    down) down ;;
    status) status ;;
    logs) logs "${2:-200}" ;;
    heartbeat) heartbeat_logs "${2:-200}" ;;
    *)
        echo "usage: selling.sh up|down|status|logs|heartbeat [lines]" >&2
        exit 2
        ;;
esac
