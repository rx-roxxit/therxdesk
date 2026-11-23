#!/usr/bin/env bash
# Puts the fixture Account at an AI-credits edge, so the gate and the
# low-credits line can be driven in the browser. The override and the reset go
# through the app's own /internal/v1 API (the way the System admin does it);
# only the ledger rows are seeded, because the dev stack has no AI key.
#
#   ai-credits.sh up     120 credits a month, none used
#   ai-credits.sh low    leave 5 of 120 credits (under 10%)
#   ai-credits.sh none   leave 0 credits (the refusal)
#   ai-credits.sh clear  remove the seeded rows and the override
#
# The app never prints a token: every call goes through internal.sh.
source "$(dirname "$0")/env.sh"

INTERNAL="$(dirname "$0")/internal.sh"
FIXTURE="$(dirname "$0")/fixture.sh"
COMPLIMENTARY="$(dirname "$0")/complimentary.sh"

json_field() {
    python3 -I -c '
import json, sys
data = json.load(sys.stdin)
for key in sys.argv[1].split("."):
    data = data[key]
print(data)
' "$1"
}

account_id() {
    "$FIXTURE" show | json_field accountId
}

# seed_usage <credits>: replace this month's ledger rows with one row.
seed_usage() {
    compose exec -T -u app app php artisan tinker --execute "
\$account = App\Models\Account::find('$(account_id)');
App\Models\AiCreditUsage::where('account_id', \$account->id)->delete();

if ($1 > 0) {
    App\Models\AiCreditUsage::create([
        'account_id' => \$account->id,
        'feature' => 'writing_assistant',
        'credits' => $1,
        'total_tokens' => $1 * 150,
    ]);
}
" >/dev/null
}

# prepare <credits>: access, the 120 override, a reset, then the usage.
prepare() {
    "$COMPLIMENTARY" up >/dev/null
    "$INTERNAL" PATCH "/accounts/$(account_id)/ai-credits" --body '{"ai_credits_per_month":120}' >/dev/null
    "$INTERNAL" POST "/accounts/$(account_id)/ai-credits/reset" >/dev/null
    seed_usage "$1"
    compose exec -T -u app app php artisan tinker --execute "
echo json_encode(app(App\Services\Ai\AiCredits::class)->payload(App\Models\Account::find('$(account_id)'))), PHP_EOL;
" | tail -1
}

case "${1:-show}" in
    up) prepare 0 ;;
    low) prepare 115 ;;
    none) prepare 120 ;;
    clear)
        seed_usage 0
        "$INTERNAL" PATCH "/accounts/$(account_id)/ai-credits" --body '{"ai_credits_per_month":null}' >/dev/null
        "$INTERNAL" POST "/accounts/$(account_id)/ai-credits/reset" >/dev/null
        "$COMPLIMENTARY" down >/dev/null
        echo "[ai-credits] cleared"
        ;;
    *)
        echo "usage: ai-credits.sh up|low|none|clear" >&2
        exit 2
        ;;
esac
