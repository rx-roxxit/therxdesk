<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\CancellationType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\DB;
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
     * Records why the subscription ended. The row is re-read under a lock, so
     * a System admin cancellation written between this handler's load and its
     * save is seen and never replaced by a later Stripe reason.
     */
    public function recordCancellation(?CancellationType $type, ?string $reason, ?CarbonInterface $at = null): void
    {
        DB::transaction(function () use ($type, $reason, $at): void {
            $stored = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($stored === null || $stored->cancellation_type === CancellationType::Admin) {
                return;
            }

            $changed = false;

            if ($type !== null && $stored->cancellation_type !== $type) {
                $stored->cancellation_type = $type;
                $changed = true;
            }

            if (filled($reason) && $stored->cancellation_reason !== $reason) {
                $stored->cancellation_reason = $reason;
                $changed = true;
            }

            if (blank($stored->cancelled_at)) {
                $stored->cancelled_at = $at ?? now();
                $changed = true;
            }

            if (! $changed) {
                return;
            }

            $stored->save();

            $this->setRawAttributes($stored->getAttributes(), true);
        });
    }

    /**
     * The Owner resumed before the period ended: the cancellation never
     * happened, so its facts go, unless the System admin recorded it.
     */
    public function clearCancellation(): void
    {
        DB::transaction(function (): void {
            $stored = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($stored === null || $stored->cancellation_type === CancellationType::Admin) {
                return;
            }

            if (blank($stored->cancellation_type) && blank($stored->cancellation_reason) && blank($stored->cancelled_at)) {
                return;
            }

            $stored->forceFill([
                'cancellation_type' => null,
                'cancellation_reason' => null,
                'cancelled_at' => null,
            ])->save();

            $this->setRawAttributes($stored->getAttributes(), true);
        });
    }
}
