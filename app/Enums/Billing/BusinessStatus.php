<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * The one billing state the daily snapshot and the live figures record for an
 * Account. Trials, Complimentary Plans, Suspended Accounts and a pending first
 * charge carry no MRR; a past_due Account that paid before and a
 * cancelling-not-ended one still do.
 */
enum BusinessStatus: string
{
    case Trialing = 'trialing';
    case Paying = 'paying';
    case PendingFirstCharge = 'pending_first_charge';
    case PastDue = 'past_due';
    case Lapsed = 'lapsed';
    case Suspended = 'suspended';
    case Complimentary = 'complimentary';

    /**
     * The states whose MRR is counted.
     */
    public function isPaying(): bool
    {
        return $this === self::Paying || $this === self::PastDue;
    }
}
