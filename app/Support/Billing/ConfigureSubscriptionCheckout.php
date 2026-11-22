<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Enums\User\Locale;
use App\Models\Account;
use App\Models\CatalogSetting;
use App\Models\Offer;
use Laravel\Cashier\SubscriptionBuilder;

final class ConfigureSubscriptionCheckout
{
    public const PLAN_METADATA_KEY = 'trypost_plan_slug';

    public const INTERVAL_METADATA_KEY = 'trypost_interval';

    public const OFFER_METADATA_KEY = 'trypost_offer_id';

    public const OFFER_SLUG_METADATA_KEY = 'trypost_offer_slug';

    /**
     * Stripe Checkout rejects subscription trials shorter than 48 hours.
     */
    public const MIN_CHECKOUT_TRIAL_DAYS = 2;

    /**
     * The trial and the discount. A first checkout gets the Catalog's trial,
     * or the offer's longer one; an Account that already had a trial gets
     * none. A trial always needs a card, so it always goes through Stripe
     * Checkout. The coupon applies whenever the offer carries one.
     *
     * Promotion codes are never allowed: an Account pays the Catalog price, or
     * what its offer grants.
     */
    public static function apply(SubscriptionBuilder $subscription, ?Offer $offer, Account $account): SubscriptionBuilder
    {
        $trialDays = self::trialDays($offer, $account);

        if ($trialDays > 0) {
            $subscription->trialDays(max(self::MIN_CHECKOUT_TRIAL_DAYS, $trialDays));
        }

        if (filled($offer?->stripe_coupon_id)) {
            $subscription->withCoupon($offer->stripe_coupon_id);
        }

        return $subscription;
    }

    /**
     * The trial a checkout gets, in days: 0 for an Account that already had
     * one, else the longer of the Catalog Trial and the offer's own.
     */
    public static function trialDays(?Offer $offer, Account $account): int
    {
        if (! $account->canStartTrial()) {
            return 0;
        }

        return max(CatalogSetting::current()->trial_days, (int) $offer?->trial_days);
    }

    /**
     * What every Checkout Session carries: the customer's language (never
     * `auto`), a required billing address, required Terms consent, and no
     * promotion code field.
     *
     * Stripe refuses a session that names both `allow_promotion_codes` and a
     * discount, so the field is left out entirely when the Offer carries a
     * coupon. Naming it `false` is not enough: Stripe counts the parameter as
     * specified.
     *
     * @return array<string, mixed>
     */
    public static function sessionOptions(Locale $locale, bool $withCoupon = false): array
    {
        $options = [
            'locale' => StripeLocale::for($locale),
            'billing_address_collection' => 'required',
            'consent_collection' => ['terms_of_service' => 'required'],
        ];

        if (! $withCoupon) {
            $options['allow_promotion_codes'] = false;
        }

        return $options;
    }
}
