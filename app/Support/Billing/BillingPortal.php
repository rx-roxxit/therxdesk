<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;

final class BillingPortal
{
    /**
     * Sends the Owner to Stripe's billing portal with the app's own
     * configuration, so its features are the same in every environment.
     */
    public static function redirect(Account $account, string $returnUrl): RedirectResponse
    {
        return $account->redirectToBillingPortal($returnUrl, [
            'configuration' => BillingPortalConfiguration::ensure(),
        ]);
    }
}
