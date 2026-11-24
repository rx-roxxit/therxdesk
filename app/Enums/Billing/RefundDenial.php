<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Exceptions\Billing\RefundFailed;

/**
 * Why a Refund and cancel is refused. `message()` is the text the endpoint
 * answered with before the decision moved here; `stripe_unavailable` only
 * labels a read that Stripe failed, so it never reaches the 422.
 */
enum RefundDenial: string
{
    case NotThai = 'not_thai';
    case NoSubscription = 'no_subscription';
    case NoStripeCustomer = 'no_stripe_customer';
    case NoRecentCharge = 'no_recent_charge';
    case NoRefundablePayment = 'no_refundable_payment';
    case StripeUnavailable = 'stripe_unavailable';

    public function message(): string
    {
        return match ($this) {
            self::NotThai => 'Refund and cancel is only available for a Thai buyer.',
            self::NoSubscription => 'This Account has no subscription to cancel.',
            self::NoStripeCustomer => 'This Account has no Stripe customer.',
            self::NoRecentCharge => 'This Account has no paid charge in the last seven days.',
            self::NoRefundablePayment => 'This charge has no refundable payment.',
            self::StripeUnavailable => RefundFailed::MESSAGE,
        };
    }
}
