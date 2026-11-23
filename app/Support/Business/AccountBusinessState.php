<?php

declare(strict_types=1);

namespace App\Support\Business;

use App\Enums\Billing\BusinessStatus;
use App\Enums\Billing\CancellationType;
use App\Enums\Billing\Interval;

/**
 * One Account's billing state on one day. Both the daily snapshot job and the
 * live Business figures are built from this, so they cannot disagree.
 */
final readonly class AccountBusinessState
{
    public function __construct(
        public string $accountId,
        public BusinessStatus $status,
        public int $mrrCents,
        public ?string $planId,
        public ?string $planName,
        public ?Interval $interval,
        public ?string $offerId,
        public ?CancellationType $cancellationType = null,
        public ?string $cancellationReason = null,
    ) {}
}
