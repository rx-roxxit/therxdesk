<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Models\Account;
use App\Models\TrialCard;

final class TrialCards
{
    /**
     * Claims the card for the Trial. True when this fingerprint never started
     * one, false when it did (a repeat card is charged at once).
     */
    public static function claim(string $fingerprint, Account $account, ?string $subscriptionId): bool
    {
        $now = now();

        return TrialCard::query()->insertOrIgnore([
            'fingerprint' => $fingerprint,
            'account_id' => $account->id,
            'stripe_subscription_id' => $subscriptionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;
    }
}
