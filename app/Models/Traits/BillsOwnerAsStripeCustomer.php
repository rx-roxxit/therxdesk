<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Support\Billing\StripeLocale;
use Laravel\Cashier\Billable;
use Stripe\Customer;

/**
 * Cashier's Billable, with the owner as the Stripe customer: their name,
 * email and the metadata from SyncsStripeCustomer.
 */
trait BillsOwnerAsStripeCustomer
{
    use Billable;

    public function stripeName(): ?string
    {
        return $this->owner?->name;
    }

    public function stripeEmail(): ?string
    {
        return $this->owner?->email;
    }

    /**
     * @return array<string, string>
     */
    public function stripeMetadata(): array
    {
        return $this->owner?->stripeMetadata() ?? [];
    }

    /**
     * The Checkout page and the customer portal open in the Owner's language.
     *
     * @return array<int, string>|null
     */
    public function stripePreferredLocales(): ?array
    {
        $locale = $this->owner?->locale;

        if (blank($locale)) {
            return null;
        }

        return [StripeLocale::for($locale)];
    }

    /**
     * @return Customer
     */
    public function syncStripeCustomerDetails()
    {
        $parameters = [
            'name' => $this->stripeName(),
            'email' => $this->stripeEmail(),
            'metadata' => $this->owner?->stripeMetadataForUpdate() ?? [],
        ];

        $preferredLocales = $this->stripePreferredLocales();

        if (filled($preferredLocales)) {
            $parameters['preferred_locales'] = $preferredLocales;
        }

        return $this->updateStripeCustomer($parameters);
    }
}
