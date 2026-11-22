<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Models\Account;
use Laravel\Cashier\Cashier;

final class OpenCheckoutSessions
{
    /**
     * Expires the customer's open Checkout Sessions before a new one starts,
     * so an earlier tab cannot complete a second subscription.
     */
    public static function expire(Account $account): void
    {
        if (blank($account->stripe_id)) {
            return;
        }

        $sessions = Cashier::stripe()->checkout->sessions->all([
            'customer' => $account->stripe_id,
            'status' => 'open',
            'limit' => 100,
        ]);

        foreach ($sessions->data as $session) {
            Cashier::stripe()->checkout->sessions->expire($session->id);
        }
    }
}
