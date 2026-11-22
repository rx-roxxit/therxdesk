<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * The kind of Stripe coupon an Offer creates: a percentage off, or an amount
 * off in USD cents.
 */
enum OfferDiscountType: string
{
    case Percent = 'percent';
    case Amount = 'amount';
}
