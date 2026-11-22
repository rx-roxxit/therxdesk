<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Offer\RedeemOffer;
use App\Enums\Billing\CancellationType;
use App\Enums\PostHog\BillingEvent;
use App\Jobs\PostHog\TrackBilling;
use App\Jobs\PostHog\TrackCheckoutCompleted;
use App\Jobs\PostHog\TrackPlanChanged;
use App\Jobs\PostHog\TrackTrialConverted;
use App\Jobs\PostHog\TrackTrialStarted;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PostHogService;
use App\Support\Billing\CardFingerprint;
use App\Support\Billing\ConfigureSubscriptionCheckout;
use App\Support\Billing\StripeValue;
use App\Support\Billing\SubscriptionPlanSync;
use App\Support\Billing\TrialCards;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;
use Stripe\Event as StripeEvent;
use Stripe\Subscription as StripeSubscription;
use Throwable;

class StripeEventListener
{
    public function handle(WebhookHandled $event): void
    {
        try {
            if ($this->alreadyProcessed(data_get($event->payload, 'id'))) {
                return;
            }

            $type = data_get($event->payload, 'type');
            $stripeCustomerId = data_get($event->payload, 'data.object.customer');

            if (! $stripeCustomerId) {
                return;
            }

            /** @var Account|null $account */
            $account = Cashier::findBillable($stripeCustomerId);

            if (! $account) {
                return;
            }

            $subscription = $this->subscriptionFor($account, $type, $event->payload);

            match ($type) {
                StripeEvent::TYPE_CUSTOMER_SUBSCRIPTION_CREATED => $this->handleSubscriptionCreated($account, $subscription, $event->payload),
                StripeEvent::TYPE_CUSTOMER_SUBSCRIPTION_UPDATED => $this->handleSubscriptionUpdated($account, $subscription, $event->payload),
                StripeEvent::TYPE_CUSTOMER_SUBSCRIPTION_DELETED => $this->handleSubscriptionDeleted($account, $subscription, $event->payload),
                default => null,
            };
        } catch (Throwable $e) {
            Log::error("Stripe webhook error: {$e->getMessage()}", [
                'exception' => $e,
                'payload' => $event->payload,
            ]);
        }
    }

    /**
     * A subscription is born either `trialing` (card-required trial, no charge
     * yet) or `active` (an offer without a trial, or no offer, charged at once): two
     * business events, see App\Support\StripeSubscriptionConversion.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionCreated(Account $account, ?Subscription $subscription, array $payload): void
    {
        $previousPlan = $account->plan?->name;
        $convertsTrialWithoutCard = $this->convertsTrialWithoutCard($account, $payload);

        $this->syncPlanFromSubscription($account, $payload);
        $this->recordFirstPayment($account, $payload);
        $this->redeemOffer($account, $payload);
        $this->recordTrialStart($account, $subscription, $payload);

        TrackBilling::dispatchIf(PostHogService::shouldTrack(), (string) $account->id, BillingEvent::Created, $payload, $previousPlan);
        TrackTrialConverted::dispatchIf(PostHogService::shouldTrack() && $convertsTrialWithoutCard, (string) $account->id, $payload, withRevenue: false);
        TrackTrialStarted::dispatchIf(PostHogService::shouldTrack() && $this->currentStatus($payload) === StripeSubscription::STATUS_TRIALING && blank($subscription?->trial_skipped_at), (string) $account->id, $payload);
        TrackCheckoutCompleted::dispatchIf(PostHogService::shouldTrack() && $this->currentStatus($payload) === StripeSubscription::STATUS_ACTIVE, (string) $account->id, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionUpdated(Account $account, ?Subscription $subscription, array $payload): void
    {
        $previousPlan = $account->plan?->name;
        $convertsTrialWithoutCard = $this->convertsTrialWithoutCard($account, $payload);

        $this->syncPlanFromSubscription($account, $payload);
        // No first payment here: a trial's end sends an `active` update
        // before Stripe attempts the charge (it turns `past_due` when that
        // charge fails), so only a paid invoice proves the Account paid. The
        // same holds for `paid_without_trial_at` (see StripeInvoiceListener).
        $this->redeemOffer($account, $payload);
        $this->recordTrialEnd($subscription, $payload);
        $this->recordCancellationFacts($subscription, $payload);

        TrackBilling::dispatchIf(PostHogService::shouldTrack(), (string) $account->id, BillingEvent::Updated, $payload, $previousPlan);
        TrackTrialConverted::dispatchIf(PostHogService::shouldTrack() && (($this->convertedFromTrial($payload) && $this->isNowActive($payload)) || $convertsTrialWithoutCard), (string) $account->id, $payload);
        TrackPlanChanged::dispatchIf(PostHogService::shouldTrack() && $this->priceChanged($payload), (string) $account->id, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleSubscriptionDeleted(Account $account, ?Subscription $subscription, array $payload): void
    {
        $this->recordTrialEnd($subscription, $payload);
        $this->recordCancellationFacts($subscription, $payload);

        if (blank($account->plan_id) && ! $account->hasPaidSubscription()) {
            return;
        }

        $previousPlan = $account->plan?->name;

        if (filled($account->plan_id) && ! $account->onGenericTrial()) {
            $account->update(['plan_id' => null]);
        }

        TrackBilling::dispatchIf(
            PostHogService::shouldTrack() && $this->claimCancellation($payload),
            (string) $account->id,
            BillingEvent::Cancelled,
            $payload,
            $previousPlan,
        );
    }

    /**
     * The local row of the Stripe subscription this webhook is about. Cashier's
     * own handler normally writes it first; tests call this listener alone, so
     * it creates a missing row here and never duplicates one.
     *
     * @param  array<string, mixed>  $payload
     */
    private function subscriptionFor(Account $account, mixed $type, array $payload): ?Subscription
    {
        if ($type !== StripeEvent::TYPE_CUSTOMER_SUBSCRIPTION_CREATED
            && $type !== StripeEvent::TYPE_CUSTOMER_SUBSCRIPTION_UPDATED
            && $type !== StripeEvent::TYPE_CUSTOMER_SUBSCRIPTION_DELETED) {
            return null;
        }

        $stripeId = data_get($payload, 'data.object.id');

        if (! is_string($stripeId) || blank($stripeId)) {
            return null;
        }

        $subscription = $account->subscriptions()->firstOrNew(['stripe_id' => $stripeId]);

        if (! $subscription->exists) {
            $subscription->type = Account::SUBSCRIPTION_NAME;
            $subscription->stripe_status = StripeSubscription::STATUS_INCOMPLETE;
        }

        $status = data_get($payload, 'data.object.status');

        if (is_string($status) && filled($status)) {
            $subscription->stripe_status = $status;
        }

        $priceId = data_get($payload, 'data.object.items.data.0.price.id');

        if (is_string($priceId) && filled($priceId)) {
            $subscription->stripe_price = $priceId;
        }

        if ($subscription->isDirty()) {
            $subscription->save();
        }

        return $subscription;
    }

    /**
     * A `trialing` subscription starts its Trial: its start is the fact that
     * outlives Cashier's `trial_ends_at` (cleared on delete), and its card is
     * claimed so a repeat card cannot use a second one.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordTrialStart(Account $account, ?Subscription $subscription, array $payload): void
    {
        if ($subscription === null) {
            return;
        }

        if ($this->currentStatus($payload) !== StripeSubscription::STATUS_TRIALING) {
            return;
        }

        $subscription->trial_started_at = StripeValue::timestamp(data_get($payload, 'data.object.trial_start')) ?? now();
        $subscription->save();

        $fingerprint = CardFingerprint::forSubscription($account, $payload);

        if (blank($fingerprint)) {
            return;
        }

        if (TrialCards::claim($fingerprint, $account, $subscription->stripe_id)) {
            return;
        }

        $this->endRepeatCardTrial($subscription);
    }

    /**
     * The card already had a Trial: Stripe ends it now, so the first charge
     * happens at once. The facts are written before the call because the
     * updated webhook it sends meanwhile has to see them; a failed call puts
     * them back and the outer handler logs it.
     */
    private function endRepeatCardTrial(Subscription $subscription): void
    {
        $previousSkippedAt = $subscription->trial_skipped_at;
        $previousEndedAt = $subscription->trial_ended_at;

        $subscription->trial_skipped_at = now();
        $subscription->trial_ended_at = now();
        $subscription->save();

        try {
            Cashier::stripe()->subscriptions->update($subscription->stripe_id, ['trial_end' => 'now']);
        } catch (Throwable $e) {
            $subscription->trial_skipped_at = $previousSkippedAt;
            $subscription->trial_ended_at = $previousEndedAt;
            $subscription->save();

            Log::warning("Stripe trial could not be ended for subscription {$subscription->stripe_id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * The Trial ended: converted to a paid subscription, or went away with
     * the subscription. A conversion ends at Stripe's `trial_end`; a deletion
     * at `ended_at` (a period-end cancel ends then, not when it was requested).
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordTrialEnd(?Subscription $subscription, array $payload): void
    {
        if ($subscription === null || ! $subscription->trialStarted() || filled($subscription->trial_ended_at)) {
            return;
        }

        $deleted = data_get($payload, 'type') === StripeEvent::TYPE_CUSTOMER_SUBSCRIPTION_DELETED;

        if (! $deleted && data_get($payload, 'data.previous_attributes.status') !== StripeSubscription::STATUS_TRIALING) {
            return;
        }

        if ($deleted) {
            $subscription->trial_ended_at = StripeValue::timestamp(data_get($payload, 'data.object.ended_at'))
                ?? StripeValue::timestamp(data_get($payload, 'data.object.canceled_at'))
                ?? now();
        } else {
            $subscription->trial_ended_at = StripeValue::timestamp(data_get($payload, 'data.object.trial_end')) ?? now();
        }

        $subscription->save();
    }

    /**
     * Records why a subscription is ending: a portal cancellation
     * (`cancel_at_period_end` plus its reason), a failed payment, or an
     * already cancelled subscription Stripe is now deleting. A resume clears
     * the facts, since the cancellation never happened.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordCancellationFacts(?Subscription $subscription, array $payload): void
    {
        if ($subscription === null) {
            return;
        }

        $cancelAtPeriodEnd = data_get($payload, 'data.object.cancel_at_period_end');
        $reason = data_get($payload, 'data.object.cancellation_details.reason');
        $canceledAt = StripeValue::timestamp(data_get($payload, 'data.object.canceled_at'));

        if (! $cancelAtPeriodEnd && blank($reason) && $canceledAt === null) {
            $subscription->clearCancellation();

            return;
        }

        $feedback = data_get($payload, 'data.object.cancellation_details.feedback');

        $subscription->recordCancellation(
            CancellationType::fromStripe(is_string($reason) ? $reason : null),
            is_string($feedback) ? $feedback : null,
            $canceledAt,
        );
    }

    /**
     * One subscription.cancelled per subscription. A dunning that ends in
     * `unpaid` clears plan_id before the delete arrives, so plan_id can no
     * longer tell a first delivery from one Stripe redelivers days later.
     *
     * @param  array<string, mixed>  $payload
     */
    private function claimCancellation(array $payload): bool
    {
        $subscriptionId = data_get($payload, 'data.object.id');

        return blank($subscriptionId) || Cache::add("posthog:subscription_cancelled:{$subscriptionId}", true, now()->addDays(30));
    }

    /**
     * Stripe redelivers the same event id on a timeout/non-2xx response (and
     * it can otherwise reach us more than once, e.g. a duplicated local
     * forwarding setup). Cache::add is atomic, so only the first delivery of
     * a given event id returns false here — every side effect further down
     * (plan_id updates, PostHog dispatches) only ever runs once per event.
     */
    private function alreadyProcessed(?string $eventId): bool
    {
        if (! $eventId) {
            return false;
        }

        return ! Cache::add("stripe_webhook_event:{$eventId}", true, now()->addDay());
    }

    /**
     * A subscription created during a trial without a card the account took
     * from an offer (`Account::subscribedDuringTrialWithoutCard()`) turns
     * active: on created, or when an incomplete one is paid. The conversion is
     * claimed atomically per subscription, so its created and updated webhooks
     * handled at the same time count it once.
     *
     * @param  array<string, mixed>  $payload
     */
    private function convertsTrialWithoutCard(Account $account, array $payload): bool
    {
        if (! $this->startsActive($payload)) {
            return false;
        }

        $subscriptionId = data_get($payload, 'data.object.id', $account->id);
        $subscribedAt = StripeValue::timestamp(data_get($payload, 'data.object.created')) ?? now();

        return $account->subscribedDuringTrialWithoutCard($subscribedAt)
            && Cache::add("posthog:trial_converted:{$subscriptionId}", true, now()->addDay());
    }

    /**
     * The subscription turns active now: created active, or paid after it was
     * created incomplete. A renewal or a plan change of an active one is not.
     *
     * @param  array<string, mixed>  $payload
     */
    private function startsActive(array $payload): bool
    {
        return $this->isNowActive($payload) && (
            data_get($payload, 'type') === StripeEvent::TYPE_CUSTOMER_SUBSCRIPTION_CREATED
            || data_get($payload, 'data.previous_attributes.status') === StripeSubscription::STATUS_INCOMPLETE
        );
    }

    /**
     * The first time a subscription is active the account has been charged:
     * kept on the account because Cashier clears `trial_ends_at` on delete.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordFirstPayment(Account $account, array $payload): void
    {
        if (! $this->isNowActive($payload) || filled($account->first_paid_at)) {
            return;
        }

        Account::withoutTimestamps(fn (): bool => $account->forceFill(['first_paid_at' => now()])->saveQuietly());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncPlanFromSubscription(Account $account, array $payload): void
    {
        $status = $this->currentStatus($payload);

        if (SubscriptionPlanSync::allows($status)) {
            $plan = $this->resolvePlanFromSubscriptionItems($payload, $account);

            if ($plan === null) {
                return;
            }

            $account->update(['plan_id' => $plan->id]);

            return;
        }

        if (SubscriptionPlanSync::clears($status) && filled($account->plan_id) && ! $account->onGenericTrial()) {
            $account->update(['plan_id' => null]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePlanFromSubscriptionItems(array $payload, Account $account): ?Plan
    {
        $priceIds = collect(data_get($payload, 'data.object.items.data', []))
            ->pluck('price.id')
            ->filter()
            ->all();

        if (empty($priceIds)) {
            return null;
        }

        $plan = Plan::forStripePrices($priceIds)->first();

        if (! $plan) {
            Log::warning('Stripe webhook: no matching plan found in subscription items', [
                'account_id' => $account->id,
                'price_ids' => $priceIds,
            ]);
        }

        return $plan;
    }

    /**
     * An offer is used once its subscription is active or trialing: an
     * incomplete one may never be paid, and would use up a place for nothing.
     *
     * @param  array<string, mixed>  $payload
     */
    private function redeemOffer(Account $account, array $payload): void
    {
        if (! SubscriptionPlanSync::allows($this->currentStatus($payload))) {
            return;
        }

        RedeemOffer::execute($account, data_get($payload, ['data', 'object', 'metadata', ConfigureSubscriptionCheckout::OFFER_METADATA_KEY]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function currentStatus(array $payload): mixed
    {
        return data_get($payload, 'data.object.status');
    }

    /**
     * True for a transition originating from a trial: either the immediate
     * `trialing` -> `active` charge, or a `past_due` -> `active` recovery
     * after the trial's first charge attempt failed. `trial_end` is never
     * cleared by Stripe once set, so it can't by itself distinguish that
     * recovery from an unrelated `past_due` -> `active` recovery on a much
     * later billing cycle (e.g. a long-time paying customer's card getting
     * declined and then updated). The item's `current_period_start` is what
     * actually pins this to the trial's own first billing period — it only
     * equals `trial_end` for that first period, never for a later one.
     *
     * @param  array<string, mixed>  $payload
     */
    private function convertedFromTrial(array $payload): bool
    {
        $previousStatus = data_get($payload, 'data.previous_attributes.status');

        if ($previousStatus === StripeSubscription::STATUS_TRIALING) {
            return true;
        }

        if ($previousStatus !== StripeSubscription::STATUS_PAST_DUE) {
            return false;
        }

        $trialEnd = data_get($payload, 'data.object.trial_end');
        $currentPeriodStart = data_get($payload, 'data.object.items.data.0.current_period_start');

        return $trialEnd !== null && $currentPeriodStart === $trialEnd;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function priceChanged(array $payload): bool
    {
        $previousPriceId = data_get($payload, 'data.previous_attributes.items.data.0.price.id');

        return filled($previousPriceId) && $previousPriceId !== data_get($payload, 'data.object.items.data.0.price.id');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isNowActive(array $payload): bool
    {
        return $this->currentStatus($payload) === StripeSubscription::STATUS_ACTIVE;
    }
}
