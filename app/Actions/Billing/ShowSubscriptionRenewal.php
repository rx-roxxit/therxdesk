<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Account;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

class ShowSubscriptionRenewal
{
    /**
     * When Stripe next charges the subscription. The period lives on each
     * subscription item (the Stripe API moved it there from the subscription),
     * and the app sells one price per subscription, so the first item is the
     * billing period.
     */
    public static function execute(Account $account, ?Subscription $subscription): ?string
    {
        if (blank($subscription) || ! $account->hasStripeId()) {
            return null;
        }

        $item = $subscription->asStripeSubscription()->items->data[0] ?? null;
        $periodEnd = $item?->current_period_end ?? null;

        if (blank($periodEnd)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $periodEnd)->toIso8601String();
    }
}
