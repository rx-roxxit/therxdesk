#!/usr/bin/env bash
# Media on R2 for the dev stack, without editing .env:
#
#   media.sh up      recreate app with FILESYSTEM_DISK=r2 (compose.r2.yaml)
#   media.sh down    remove the r2 marker and recreate app from .env's disk
#   media.sh seed    upload the proof avatar and write the follower readings
#   media.sh clear   remove the proof channel, its readings and the avatar
#
# Seed draws on the dev fixture's Owner: fixture.sh up first. The journey that
# proves uploads, previews and the share card is `drive.sh media-on-r2`; it
# needs a subscribed Owner (welcome-checkout) and runs in selling mode.
# See features/media-on-r2.md.
source "$(dirname "$0")/env.sh"

served_disk() {
    app_run php artisan tinker --execute='echo config("filesystems.default");' 2>/dev/null | tr -d '\r\n'
}

disk_is() {
    [ "$(served_disk)" = "$1" ]
}

up() {
    if [ -z "$(compose ps --status running -q app)" ]; then
        echo "[media] the dev stack is not running: run launch.sh first" >&2
        exit 1
    fi

    mkdir -p "$STATE_DIR"
    touch "$R2_MARKER"
    echo "[media] recreating app with FILESYSTEM_DISK=r2"
    compose up -d app

    wait_until_usable
    wait_for "php-fpm serves the r2 disk" 60 disk_is r2
    echo "[media] media is on r2; media.sh down returns to .env's disk"
}

down() {
    if [ ! -f "$R2_MARKER" ]; then
        echo "[media] not on r2 (no marker): nothing to do"
        return
    fi

    rm -f "$R2_MARKER"
    echo "[media] recreating app from .env's disk"
    compose up -d app

    wait_until_usable
    echo "[media] php-fpm serves disk: $(served_disk)"
}

case "${1:-seed}" in
    up) up ;;
    down) down ;;
    seed|clear)
        compose run --rm -T -u app app php "$SKILL_IN_APP/scripts/media.php" "$1"
        ;;
    *)
        echo "usage: media.sh up|down|seed|clear" >&2
        exit 2
        ;;
esac
