<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use RuntimeException;
use Throwable;

/**
 * The webhook answers non-2xx so Stripe redelivers the event, whose steps are
 * idempotent.
 */
class TrialEndFailed extends RuntimeException
{
    public function __construct(?string $subscriptionId, Throwable $previous)
    {
        parent::__construct("Stripe trial could not be ended for subscription {$subscriptionId}: {$previous->getMessage()}", previous: $previous);
    }
}
