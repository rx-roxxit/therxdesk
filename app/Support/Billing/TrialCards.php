<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Enums\Billing\TrialClaim;
use App\Models\Account;
use App\Models\TrialCard;

final class TrialCards
{
    /**
     * Claims the card for the Trial. `insertOrIgnore` on the fingerprint is
     * atomic; a row this subscription wrote itself means the same event was
     * delivered again, not a repeat card.
     */
    public static function claim(string $fingerprint, Account $account, ?string $subscriptionId): TrialClaim
    {
        $now = now();

        $claimed = TrialCard::query()->insertOrIgnore([
            'fingerprint' => $fingerprint,
            'account_id' => $account->id,
            'stripe_subscription_id' => $subscriptionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;

        if ($claimed) {
            return TrialClaim::Claimed;
        }

        $owner = TrialCard::query()->whereKey($fingerprint)->value('stripe_subscription_id');

        if (filled($subscriptionId) && $owner === $subscriptionId) {
            return TrialClaim::Owned;
        }

        return TrialClaim::Taken;
    }
}
