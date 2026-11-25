<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Actions\Account\SyncDeletionClock;
use App\Enums\Billing\CancellationType;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Billing\StripeValue;
use App\Support\Billing\SubscriptionPlanSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Subscription as StripeSubscription;

/**
 * Rebuilds the facts a missed Stripe webhook leaves out. Stripe is the source
 * of truth: each Account's Subscription is read back and the local plan,
 * Trial dates, cancellation reason and paid invoices are filled in where they
 * are missing. Every step is idempotent, so the daily run repairs only what
 * differs and a redelivery is never needed.
 */
class BillingReconcileCommand extends Command
{
    protected $signature = 'rxdesk:billing-reconcile
        {--account= : Only this Account UUID}
        {--dry-run : Report what would change without saving it}';

    protected $description = 'Re-read Stripe subscriptions and repair plan, trial and paid facts';

    public function handle(): int
    {
        if (config('trypost.self_hosted')) {
            $this->info('Self-hosted: billing reconciliation is off.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $query = Account::query()
            ->whereNotNull('stripe_id')
            ->with(['subscriptions', 'plan']);

        if (filled($this->option('account'))) {
            $query->whereKey((string) $this->option('account'));
        }

        $repaired = 0;
        $failed = 0;

        $query->chunkById(200, function ($accounts) use (&$repaired, &$failed, $dryRun): void {
            foreach ($accounts as $account) {
                try {
                    if (! $this->reconcile($account, $dryRun)) {
                        continue;
                    }

                    $repaired++;

                    $this->line(($dryRun ? 'Would repair ' : 'Repaired ')."Account {$account->id}.");
                } catch (ApiErrorException $exception) {
                    $failed++;

                    $this->warn("Account {$account->id}: {$exception->getMessage()}");
                }
            }
        });

        $this->info($dryRun
            ? "{$repaired} Accounts would be repaired; {$failed} Stripe reads failed."
            : "{$repaired} Accounts repaired; {$failed} Stripe reads failed.");

        return self::SUCCESS;
    }

    private function reconcile(Account $account, bool $dryRun): bool
    {
        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);

        if ($subscription === null) {
            return false;
        }

        $stripe = Cashier::stripe()->subscriptions->retrieve($subscription->stripe_id)->toArray();

        DB::beginTransaction();

        try {
            $changed = $this->syncSubscription($account, $subscription, $stripe);
            $changed = $this->syncPaidInvoice($account, $subscription) || $changed;

            if ($changed) {
                SyncDeletionClock::execute($account->fresh());
            }
        } finally {
            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $stripe
     */
    private function syncSubscription(Account $account, Subscription $subscription, array $stripe): bool
    {
        $changed = false;
        $status = data_get($stripe, 'status');

        if (is_string($status) && filled($status) && $subscription->stripe_status !== $status) {
            $subscription->stripe_status = $status;
            $changed = true;
        }

        $changed = $this->syncPlan($account, $status, $stripe) || $changed;
        $changed = $this->syncTrial($subscription, $stripe) || $changed;

        // Save before recordCancellation(), which rewrites this model's
        // attributes from the locked row and would drop them otherwise.
        if ($subscription->isDirty()) {
            $subscription->save();
        }

        if ($account->isDirty()) {
            $account->save();
        }

        return $this->syncCancellation($subscription, $status, $stripe) || $changed;
    }

    /**
     * @param  array<string, mixed>  $stripe
     */
    private function syncPlan(Account $account, mixed $status, array $stripe): bool
    {
        if (SubscriptionPlanSync::allows($status)) {
            $priceIds = collect(data_get($stripe, 'items.data', []))->pluck('price.id')->filter()->all();
            $plan = empty($priceIds) ? null : Plan::forStripePrices($priceIds)->first();

            if ($plan !== null && $account->plan_id !== $plan->id) {
                $account->plan_id = $plan->id;

                return true;
            }

            return false;
        }

        if (SubscriptionPlanSync::clears($status) && filled($account->plan_id) && ! $account->onGenericTrial()) {
            $account->plan_id = null;

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $stripe
     */
    private function syncTrial(Subscription $subscription, array $stripe): bool
    {
        $changed = false;
        $start = StripeValue::timestamp(data_get($stripe, 'trial_start'));
        $end = StripeValue::timestamp(data_get($stripe, 'trial_end'));

        if ($start !== null && blank($subscription->trial_started_at)) {
            $subscription->trial_started_at = $start;
            $changed = true;
        }

        if (filled($subscription->trial_started_at) && blank($subscription->trial_ended_at) && $end !== null && $end->isPast()) {
            $subscription->trial_ended_at = $end;
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $stripe
     */
    private function syncCancellation(Subscription $subscription, mixed $status, array $stripe): bool
    {
        $canceledAt = StripeValue::timestamp(data_get($stripe, 'canceled_at'));
        $cancelAt = StripeValue::timestamp(data_get($stripe, 'cancel_at'));
        $cancelAtPeriodEnd = (bool) data_get($stripe, 'cancel_at_period_end');

        $changed = false;

        if (($canceledAt !== null || $cancelAtPeriodEnd) && blank($subscription->cancelled_at)) {
            $reason = data_get($stripe, 'cancellation_details.reason');
            $feedback = data_get($stripe, 'cancellation_details.feedback');

            $subscription->recordCancellation(
                CancellationType::fromStripe(is_string($reason) ? $reason : null),
                is_string($feedback) ? $feedback : null,
                $canceledAt,
            );

            $changed = true;
        }

        // Set the end after recordCancellation(), which reloads the row.
        if (blank($subscription->ends_at)) {
            if ($status === StripeSubscription::STATUS_CANCELED) {
                $subscription->ends_at = $canceledAt ?? now();
                $changed = true;
            } elseif ($cancelAtPeriodEnd && $cancelAt !== null) {
                $subscription->ends_at = $cancelAt;
                $changed = true;
            }
        }

        if ($subscription->isDirty()) {
            $subscription->save();
        }

        return $changed;
    }

    /**
     * The first paid invoice and the repeat card's charge, both read back from
     * Stripe so a lost `invoice.payment_succeeded` cannot leave them out.
     */
    private function syncPaidInvoice(Account $account, Subscription $subscription): bool
    {
        $needsFirst = blank($account->first_paid_at);
        $needsRepeat = filled($subscription->trial_skipped_at) && blank($subscription->paid_without_trial_at);

        if (! $needsFirst && ! $needsRepeat) {
            return false;
        }

        $invoices = Cashier::stripe()->invoices->all([
            'customer' => $account->stripe_id,
            'status' => 'paid',
            'limit' => 100,
        ]);

        $paid = collect($invoices->data)
            ->filter(fn ($invoice): bool => (int) data_get($invoice, 'amount_paid', 0) > 0)
            ->sortBy(fn ($invoice): int => (int) (data_get($invoice, 'created') ?? 0))
            ->values();

        if ($paid->isEmpty()) {
            return false;
        }

        $changed = false;

        if ($needsFirst) {
            $first = $paid->first();

            $account->forceFill([
                'first_invoice_id' => data_get($first, 'id'),
                'first_paid_at' => StripeValue::timestamp(data_get($first, 'status_transitions.paid_at')) ?? now(),
            ])->save();

            $changed = true;
        }

        if ($needsRepeat) {
            $match = $paid->first(fn ($invoice): bool => $this->invoiceSubscriptionId($invoice->toArray()) === $subscription->stripe_id);

            if ($match !== null) {
                $subscription->forceFill([
                    'paid_without_trial_at' => StripeValue::timestamp(data_get($match, 'status_transitions.paid_at')) ?? now(),
                ])->save();

                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function invoiceSubscriptionId(array $invoice): ?string
    {
        $subscription = data_get($invoice, 'subscription')
            ?? data_get($invoice, 'parent.subscription_details.subscription');

        if (is_string($subscription) && filled($subscription)) {
            return $subscription;
        }

        $id = data_get($subscription, 'id');

        return is_string($id) && filled($id) ? $id : null;
    }
}
