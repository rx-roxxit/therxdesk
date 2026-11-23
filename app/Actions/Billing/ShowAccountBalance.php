<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Account;
use App\Support\Billing\OfferCopy;

class ShowAccountBalance
{
    /**
     * The credit the Account holds on its Stripe customer, as a positive
     * amount the billing page shows. A downgrade leaves the unused time here
     * (ADR 0010: a Plan change never refunds cash).
     *
     * Stripe stores credit as a negative customer balance; an amount owed is
     * positive, is not money the Account can spend, and is left out.
     */
    public static function execute(Account $account): ?string
    {
        if (! $account->hasStripeId()) {
            return null;
        }

        $balance = (int) ($account->asStripeCustomer()->balance ?? 0);

        if ($balance >= 0) {
            return null;
        }

        return OfferCopy::money(-$balance);
    }
}
