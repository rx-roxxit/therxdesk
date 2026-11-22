<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Why a subscription ended: the Owner cancelled it in the portal, its payment
 * failed, or the System admin ended it.
 */
enum CancellationType: string
{
    case Voluntary = 'voluntary';
    case PaymentFailed = 'payment_failed';
    case Admin = 'admin';

    /**
     * The cancellation facts of a Stripe subscription, null when Stripe gives
     * no reason we track.
     */
    public static function fromStripe(?string $reason): ?self
    {
        if ($reason === 'cancellation_requested') {
            return self::Voluntary;
        }

        if ($reason === 'payment_failed') {
            return self::PaymentFailed;
        }

        return null;
    }
}
