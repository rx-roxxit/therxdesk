#!/usr/bin/env bash
# Seeds the media-on-R2 proof on the dev fixture: an analytics-included
# Instagram channel whose avatar lives on the default (r2) disk, plus two
# follower readings so the Insights share card draws that avatar. Run it with
# the selling overlay up and FILESYSTEM_DISK=r2 (see features/media-on-r2.md).
#
#   media.sh seed    upload the avatar and write the readings (idempotent)
#   media.sh clear   remove the channel, its readings and the avatar
source "$(dirname "$0")/env.sh"

compose run --rm -T -u app app php "$SKILL_IN_APP/scripts/media.php" "${1:-seed}"
