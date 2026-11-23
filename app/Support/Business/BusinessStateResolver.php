<?php

declare(strict_types=1);

namespace App\Support\Business;

use App\Enums\Billing\BusinessStatus;
use App\Enums\Billing\Interval;
use App\Enums\Billing\OfferDuration;
use App\Models\Account;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Support\Billing\OfferPricing;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Stripe\Subscription as StripeSubscription;

/**
 * The one place an Account's billing state and MRR for a day are computed.
 * The daily snapshot job and today's live Business figures both call
 * `stateFor()`, so the two can never disagree.
 *
 * MRR is the next renewal's price after the Offer in force, monthly-normalized
 * (a yearly price divided by 12). Trials, Complimentary Plans, Suspended
 * Accounts and a pending first charge carry none; a past_due Account that paid
 * before and a cancelling-not-ended one do. The Account balance is ignored and
 * a 100% Offer is 0.
 */
final class BusinessStateResolver
{
    /**
     * Offers are read once per resolver instance, never per Account.
     *
     * @var array<string, Offer|null>
     */
    private array $offers = [];

    public function stateFor(Account $account, CarbonInterface $at): AccountBusinessState
    {
        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);
        $price = $this->priceRow($subscription);
        $plan = $price?->plan ?? $account->plan;
        $interval = $price?->interval ?? ($subscription !== null ? Interval::Monthly : null);
        $offer = $this->offerFor($account, $subscription, $plan, $interval, $at);

        $state = fn (BusinessStatus $status, int $mrr): AccountBusinessState => new AccountBusinessState(
            accountId: (string) $account->id,
            status: $status,
            mrrCents: $mrr,
            planId: $plan?->id,
            planName: $plan?->name,
            interval: $interval,
            offerId: $offer?->id,
            cancellationType: $subscription?->cancellation_type,
            cancellationReason: $subscription?->cancellation_reason,
        );

        if ($account->isSuspended()) {
            return $state(BusinessStatus::Suspended, 0);
        }

        if ($account->isComplimentary()) {
            return $state(BusinessStatus::Complimentary, 0);
        }

        if ($this->isTrialing($account, $subscription)) {
            return $state(BusinessStatus::Trialing, 0);
        }

        if ($account->hasPendingFirstCharge()) {
            return $state(BusinessStatus::PendingFirstCharge, 0);
        }

        // Stripe's own `canceled` status is terminal. Cashier treats a row as
        // active while its `ends_at` is null (a cancelled-at-period-end
        // subscription keeps both), so the status is checked here too.
        if ($subscription !== null
            && $subscription->stripe_status === StripeSubscription::STATUS_CANCELED
            && ! $subscription->onGracePeriod()) {
            return $state(BusinessStatus::Lapsed, 0);
        }

        if ($subscription !== null && $subscription->pastDue()) {
            return $state(BusinessStatus::PastDue, $this->mrr($price, $plan, $interval, $offer));
        }

        if ($subscription !== null && $subscription->valid()) {
            return $state(BusinessStatus::Paying, $this->mrr($price, $plan, $interval, $offer));
        }

        return $state(BusinessStatus::Lapsed, 0);
    }

    /**
     * The Accounts the Business figures describe, with the relations a state
     * needs already loaded.
     */
    public function accounts(): Builder
    {
        return Account::query()
            ->withBillingFootprint()
            ->with(['plan.prices', 'subscriptions', 'redeemedOffers']);
    }

    private function isTrialing(Account $account, ?Subscription $subscription): bool
    {
        if ($account->onGenericTrial()) {
            return true;
        }

        if ($subscription === null) {
            return false;
        }

        return $subscription->onTrial()
            || $subscription->stripe_status === StripeSubscription::STATUS_TRIALING;
    }

    private function priceRow(?Subscription $subscription): ?PlanPrice
    {
        $stripePrice = $subscription?->stripe_price;

        if (blank($stripePrice)) {
            return null;
        }

        return PlanPrice::query()
            ->with('plan.prices')
            ->where('stripe_price_id', $stripePrice)
            ->first();
    }

    private function mrr(?PlanPrice $price, ?Plan $plan, ?Interval $interval, ?Offer $offer): int
    {
        if ($interval === null) {
            return 0;
        }

        $amount = $price?->amount ?? $plan?->priceFor($interval)?->amount;

        if ($amount === null) {
            return 0;
        }

        $amount = (int) $amount;

        if ($offer !== null) {
            $amount = OfferPricing::discountedAmount($amount, $offer);
        }

        if ($interval === Interval::Yearly) {
            return (int) round($amount / 12);
        }

        return $amount;
    }

    private function offerFor(
        Account $account,
        ?Subscription $subscription,
        ?Plan $plan,
        ?Interval $interval,
        CarbonInterface $at,
    ): ?Offer {
        if ($plan === null || $interval === null) {
            return null;
        }

        $offer = $this->offerFromSubscription($subscription) ?? $this->latestRedeemedOffer($account);

        if ($offer === null) {
            return null;
        }

        if (! $offer->covers($plan, $interval)) {
            return null;
        }

        if (! $this->offerAppliesAtNextRenewal($account, $subscription, $interval, $offer, $at)) {
            return null;
        }

        return $offer;
    }

    /**
     * The Offer a subscription carried in its own Stripe metadata
     * (`trypost_offer_id`), copied to our database by the webhook.
     */
    private function offerFromSubscription(?Subscription $subscription): ?Offer
    {
        $offerId = $subscription?->offer_id;

        if (blank($offerId)) {
            return null;
        }

        return $this->offerById((string) $offerId);
    }

    /**
     * A subscription that predates the metadata column falls back to the last
     * Offer the Account redeemed.
     */
    private function latestRedeemedOffer(Account $account): ?Offer
    {
        if ($account->relationLoaded('redeemedOffers')) {
            return $account->redeemedOffers->sortByDesc('pivot.redeemed_at')->first();
        }

        return $account->latestRedeemedOffer();
    }

    private function offerById(string $offerId): ?Offer
    {
        if (! array_key_exists($offerId, $this->offers)) {
            $this->offers[$offerId] = Offer::query()->find($offerId);
        }

        return $this->offers[$offerId];
    }

    /**
     * Whether the Offer is still in force at the next renewal: it covers the
     * Plan and interval (checked by the caller), it has started, it has not
     * ended, and its duration has not run out. A `once` Offer applies to the
     * first charge only; a `repeating` one for its months of charges; a
     * `forever` one for as long as it runs.
     */
    private function offerAppliesAtNextRenewal(
        Account $account,
        ?Subscription $subscription,
        Interval $interval,
        Offer $offer,
        CarbonInterface $at,
    ): bool {
        if (filled($offer->starts_at) && $offer->starts_at->greaterThan($at)) {
            return false;
        }

        $intervalMonths = $interval === Interval::Yearly ? 12 : 1;
        $charged = $this->hasCharged($account, $subscription);
        $firstChargeAt = $this->firstChargeAt($account, $subscription);

        if ($charged) {
            $monthsSince = $firstChargeAt !== null ? (int) floor($firstChargeAt->diffInMonths($at)) : 0;
            $periodsElapsed = intdiv($monthsSince, $intervalMonths);
            $renewalBase = $firstChargeAt ?? $at;
            $nextRenewalAt = $renewalBase->copy()->addMonthsNoOverflow(($periodsElapsed + 1) * $intervalMonths);
            $monthsAtNext = $monthsSince + $intervalMonths;
        } else {
            $nextRenewalAt = $subscription?->trial_ends_at ?? $at;
            $monthsAtNext = 0;
        }

        if (filled($offer->ends_at) && $offer->ends_at->lessThan($nextRenewalAt)) {
            return false;
        }

        return match ($offer->duration) {
            OfferDuration::Once => ! $charged,
            OfferDuration::Repeating => $monthsAtNext < (int) ($offer->duration_in_months ?? 0),
            OfferDuration::Forever => true,
            null => false,
        };
    }

    /**
     * Charged at least once on this subscription. An Account that paid before
     * never gets a second Trial, so a later subscription is charged at once.
     */
    private function hasCharged(Account $account, ?Subscription $subscription): bool
    {
        if (! $account->hasPaidSubscription()) {
            return false;
        }

        if ($this->isTrialing($account, $subscription)) {
            return false;
        }

        return ! $account->hasPendingFirstCharge();
    }

    /**
     * The subscription's first charge, used to count the months a repeating
     * Offer has already covered. A subscription created after the Account's
     * first payment starts its own count.
     */
    private function firstChargeAt(Account $account, ?Subscription $subscription): ?CarbonInterface
    {
        if ($subscription?->paid_without_trial_at !== null) {
            return $subscription->paid_without_trial_at;
        }

        if ($subscription?->trial_ended_at !== null) {
            return $subscription->trial_ended_at;
        }

        if ($subscription !== null && $account->first_paid_at !== null) {
            $createdAt = $subscription->created_at;

            if ($createdAt !== null && $account->first_paid_at->lessThan($createdAt)) {
                return $createdAt;
            }
        }

        return $account->first_paid_at;
    }
}
