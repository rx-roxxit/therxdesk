<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Enums\Billing\TrialClaim;
use App\Models\Account;
use App\Models\TrialAccount;

final class TrialAccounts
{
    /**
     * Claims the Account's one Trial. `insertOrIgnore` on the Account id is
     * atomic, so two subscriptions created at the same time cannot both
     * keep a Trial.
     */
    public static function claim(Account $account, ?string $subscriptionId): TrialClaim
    {
        $now = now();

        $claimed = TrialAccount::query()->insertOrIgnore([
            'account_id' => $account->id,
            'stripe_subscription_id' => $subscriptionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;

        if ($claimed) {
            return TrialClaim::Claimed;
        }

        $owner = TrialAccount::query()->whereKey($account->id)->value('stripe_subscription_id');

        if (filled($subscriptionId) && $owner === $subscriptionId) {
            return TrialClaim::Owned;
        }

        return TrialClaim::Taken;
    }
}
