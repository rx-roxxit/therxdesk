#!/usr/bin/env bash
# Switches the running dev stack between self-hosted mode (what .env says) and
# selling mode (SELF_HOSTED=false, Stripe sandbox, webhooks forwarded by the
# Stripe CLI), without editing .env. See compose.selling.yaml.
#
#   selling.sh up       sandbox catalog + Plans, webhook secret, recreate app in selling mode, start the listener
#   selling.sh down     stop the listener, recreate app from .env (self-hosted)
#   selling.sh status   the marker, the app container's SELF_HOSTED, what php-fpm serves, the listener
#   selling.sh logs     the listener's log, with the signing secret redacted
#
# Never prints STRIPE_SECRET or the webhook signing secret.
source "$(dirname "$0")/env.sh"

STRIPE="$(dirname "$0")/stripe.sh"

# The Plans of the CURRENT code (upstream's Socials and Workspaces, prices
# from lang/en/billing.php), as "<plan slug> <interval> <amount in cents>".
# Temporary: once the app's own Plan prices (plan_prices) and Stripe sync
# ship, seed from those instead (see SKILL.md, "Selling mode").
CATALOG=(
    "socials month 1900"
    "socials year 19000"
    "workspaces month 9900"
    "workspaces year 99000"
)

json_field() {
    python3 -I -c "import json,sys; d=json.load(sys.stdin); print($1)"
}

# Prints the id of the sandbox price with this lookup key, creating its
# product and the price when missing. Idempotent: a second run finds them.
ensure_price() {
    local slug="$1" interval="$2" amount="$3"
    local lookup="rxdesk-verify-${slug}-${interval}ly"
    local found product

    found="$("$STRIPE" get /v1/prices -d "lookup_keys[]=$lookup" -d active=true | json_field 'd["data"][0]["id"] if d["data"] else ""')"
    if [ -n "$found" ]; then
        echo "$found"
        return
    fi

    # Reuse the product a sibling price of the same plan already has.
    product="$("$STRIPE" get /v1/prices -d "lookup_keys[]=rxdesk-verify-${slug}-monthly" -d "lookup_keys[]=rxdesk-verify-${slug}-yearly" \
        | json_field 'd["data"][0]["product"] if d["data"] else ""')"
    if [ -z "$product" ]; then
        local name
        name="$(python3 -I -c "import sys; print(sys.argv[1].capitalize())" "$slug")"
        product="$("$STRIPE" post /v1/products -d "name=$name" -d "metadata[rxdesk_verify]=true" | json_field 'd["id"]')"
        echo "[selling] created sandbox product $product ($name)" >&2
    fi

    found="$("$STRIPE" post /v1/prices -d "product=$product" -d currency=usd -d "unit_amount=$amount" \
        -d "recurring[interval]=$interval" -d "lookup_key=$lookup" -d "metadata[rxdesk_verify]=true" | json_field 'd["id"]')"
    echo "[selling] created sandbox price $found ($lookup)" >&2
    echo "$found"
}

seed_plans() {
    local env_args=() line slug interval amount id
    for line in "${CATALOG[@]}"; do
        read -r slug interval amount <<<"$line"
        id="$(ensure_price "$slug" "$interval" "$amount")"
        [[ "$id" == price_* ]] || { echo "[selling] FAILED: no sandbox price for $slug $interval" >&2; exit 1; }
        env_args+=(-e "STRIPE_$(echo "$slug" | tr a-z A-Z)_$(echo "${interval}ly" | tr a-z A-Z)=$id")
        echo "[selling] price $slug ${interval}ly: $id"
    done

    # PlanSeeder reads the price ids from the environment; they go to this
    # one-off command only, never into .env.
    compose run --rm -T -u app "${env_args[@]}" app php artisan db:seed --class='Database\Seeders\PlanSeeder' --force
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

# grep reads the whole log (no -q): with pipefail, -q closing the pipe early
# would fail the pipeline on SIGPIPE.
listener_ready() {
    compose logs --no-log-prefix stripe 2>/dev/null | grep 'Ready!' >/dev/null
}

served_mode_is() {
    [ "$(served_mode)" = "$1" ]
}

up() {
    if [ -z "$(compose ps --status running -q app)" ]; then
        echo "[selling] the dev stack is not running: run launch.sh first" >&2
        exit 1
    fi

    seed_plans
    write_webhook_secret

    touch "$SELLING_MARKER"
    echo "[selling] recreating app in selling mode and starting the Stripe listener"
    compose up -d app stripe

    wait_until_usable
    wait_for "php-fpm serves selling mode (selfHosted=false)" 60 served_mode_is selling
    wait_for "Stripe listener ready" 60 listener_ready
    echo "[selling] selling mode is up; selling.sh down returns to .env's mode"
}

down() {
    if [ ! -f "$SELLING_MARKER" ]; then
        echo "[selling] not in selling mode (no marker): nothing to do"
        return
    fi

    echo "[selling] stopping the Stripe listener"
    compose rm -sf stripe
    rm -f "$SELLING_MARKER"

    echo "[selling] recreating app from .env"
    compose up -d app
    rm -f "$SELLING_SECRET_FILE"

    wait_until_usable
    echo "[selling] php-fpm serves: $(served_mode)"
}

status() {
    local app_id listener container_mode
    if [ -f "$SELLING_MARKER" ]; then echo "marker    selling ($SELLING_MARKER)"; else echo "marker    none (self-hosted per .env)"; fi

    app_id="$(compose ps --status running -q app 2>/dev/null)"
    if [ -n "$app_id" ]; then
        container_mode="$(docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "$app_id" | sed -n 's/^SELF_HOSTED=//p' | head -1)"
        echo "container SELF_HOSTED=${container_mode:-<not set: .env decides>}"
    else
        echo "container app not running"
    fi

    echo "served    $(served_mode)"

    listener="$(docker compose --progress quiet --project-directory "$MAIN_CHECKOUT" \
        -f "$MAIN_CHECKOUT/compose.yaml" -f "$MAIN_CHECKOUT/compose.override.yaml" -f "$SELLING_OVERLAY" \
        ps --status running -q stripe 2>/dev/null || true)"
    if [ -n "$listener" ]; then echo "listener  running"; else echo "listener  not running"; fi
}

logs() {
    compose logs --no-log-prefix --tail="${1:-200}" stripe | sed -E 's/whsec_[A-Za-z0-9]+/whsec_<redacted>/g'
}

case "${1:-status}" in
    up) up ;;
    down) down ;;
    status) status ;;
    logs) logs "${2:-200}" ;;
    *)
        echo "usage: selling.sh up|down|status|logs [lines]" >&2
        exit 2
        ;;
esac
