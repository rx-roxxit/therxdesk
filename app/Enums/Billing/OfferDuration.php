<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * How long a coupon lasts, as Stripe defines it: the first charge only, the
 * first N months of charges, or every charge while the subscription lives.
 */
enum OfferDuration: string
{
    case Once = 'once';
    case Repeating = 'repeating';
    case Forever = 'forever';

    public function isRepeating(): bool
    {
        return $this === self::Repeating;
    }
}
