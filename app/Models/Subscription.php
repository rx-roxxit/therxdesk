<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\CancellationType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Cashier\Subscription as CashierSubscription;
use Stripe\Subscription as StripeSubscription;

class Subscription extends CashierSubscription
{
    use HasUuids;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'ends_at' => 'datetime',
        'quantity' => 'integer',
        'trial_ends_at' => 'datetime',
        'trial_started_at' => 'datetime',
        'trial_ended_at' => 'datetime',
        'trial_skipped_at' => 'datetime',
        'paid_without_trial_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancellation_type' => CancellationType::class,
    ];

    /** Incomplete / incomplete_expired never became billable. */
    public function becameBillable(): bool
    {
        return ! $this->incomplete() && $this->stripe_status !== StripeSubscription::STATUS_INCOMPLETE_EXPIRED;
    }

    /**
     * The subscription started a Trial, whatever its state now. Stripe clears
     * `trial_ends_at` on deletion, so `trial_started_at` is the lasting fact.
     */
    public function trialStarted(): bool
    {
        return filled($this->trial_started_at);
    }

    /**
     * Records why the subscription ended. A System admin's cancellation is
     * never replaced by a later Stripe reason.
     */
    public function recordCancellation(?CancellationType $type, ?string $reason, ?CarbonInterface $at = null): void
    {
        $changed = false;

        if ($type !== null && $this->cancellation_type !== CancellationType::Admin && $this->cancellation_type !== $type) {
            $this->cancellation_type = $type;
            $changed = true;
        }

        if (filled($reason) && $this->cancellation_reason !== $reason) {
            $this->cancellation_reason = $reason;
            $changed = true;
        }

        if (blank($this->cancelled_at)) {
            $this->cancelled_at = $at ?? now();
            $changed = true;
        }

        if ($changed) {
            $this->save();
        }
    }

    /**
     * The Owner resumed before the period ended: the cancellation never
     * happened, so its facts go.
     */
    public function clearCancellation(): void
    {
        if (blank($this->cancellation_type) && blank($this->cancellation_reason) && blank($this->cancelled_at)) {
            return;
        }

        $this->cancellation_type = null;
        $this->cancellation_reason = null;
        $this->cancelled_at = null;
        $this->save();
    }
}
