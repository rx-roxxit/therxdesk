<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Account;
use App\Support\Billing\BlockedCountries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Event as StripeEvent;
use Stripe\Subscription as StripeSubscription;
use Throwable;

/**
 * A completed Checkout whose billing country The RX Desk does not sell in
 * (the EU/UK block) is cancelled at once, still in its Trial, so nothing is
 * charged. The Account keeps the country as a fact, and the plan step shows
 * the notice. The register form already refuses a blocked country; this is
 * the guard for a false or changed billing address at Stripe.
 */
class StripeCheckoutSessionListener
{
    public function handle(WebhookReceived $event): void
    {
        if (config('trypost.self_hosted')) {
            return;
        }

        if (data_get($event->payload, 'type') !== StripeEvent::TYPE_CHECKOUT_SESSION_COMPLETED) {
            return;
        }

        if (data_get($event->payload, 'data.object.mode') !== StripeCheckoutSession::MODE_SUBSCRIPTION) {
            return;
        }

        // Own key: this listener and StripeBillingSignalListener both see every
        // webhook on WebhookReceived, so a shared key would consume the other.
        $eventId = data_get($event->payload, 'id');
        $cacheKey = is_string($eventId) && filled($eventId) ? "stripe_checkout_session:{$eventId}" : null;

        if ($cacheKey !== null && ! Cache::add($cacheKey, true, now()->addDay())) {
            return;
        }

        try {
            $country = data_get($event->payload, 'data.object.customer_details.address.country');

            if (! BlockedCountries::isBlocked(is_string($country) ? $country : null)) {
                return;
            }

            $stripeCustomerId = data_get($event->payload, 'data.object.customer');

            if (! is_string($stripeCustomerId) || blank($stripeCustomerId)) {
                return;
            }

            /** @var Account|null $account */
            $account = Cashier::findBillable($stripeCustomerId);

            if (! $account) {
                return;
            }

            $this->recordBlock($account, (string) $country, data_get($event->payload, 'data.object.payment_status'));

            $this->cancelSubscription($account, data_get($event->payload, 'data.object.subscription'));
        } catch (Throwable $e) {
            // Release the key so Stripe's redelivery runs the cancel again; a
            // failed cancel is never swallowed.
            if ($cacheKey !== null) {
                Cache::forget($cacheKey);
            }

            Log::error("Stripe checkout session listener error: {$e->getMessage()}", [
                'exception' => $e,
                'payload' => $event->payload,
            ]);

            throw $e;
        }
    }

    /**
     * The first fact wins: the country, when it was seen, and the raw payment
     * status Stripe reported (a fact, never a guess).
     */
    private function recordBlock(Account $account, string $country, mixed $paymentStatus): void
    {
        if (filled($account->blocked_country_at)) {
            return;
        }

        $account->forceFill([
            'blocked_country' => Str::upper(trim($country)),
            'blocked_country_at' => now(),
            'blocked_country_payment_status' => is_string($paymentStatus) ? $paymentStatus : null,
        ])->save();
    }

    /**
     * Cancels at Stripe (no refund call), then ends the local subscription so
     * access stops before the deleted webhook arrives.
     */
    private function cancelSubscription(Account $account, mixed $stripeSubscriptionId): void
    {
        if (! is_string($stripeSubscriptionId) || blank($stripeSubscriptionId)) {
            return;
        }

        $subscription = Cashier::stripe()->subscriptions->retrieve($stripeSubscriptionId);

        $status = $subscription->status;

        if ($status !== StripeSubscription::STATUS_CANCELED && $status !== StripeSubscription::STATUS_INCOMPLETE_EXPIRED) {
            Cashier::stripe()->subscriptions->cancel($stripeSubscriptionId);
        }

        $account->subscriptions()
            ->where('stripe_id', $stripeSubscriptionId)
            ->update([
                'stripe_status' => StripeSubscription::STATUS_CANCELED,
                'ends_at' => now(),
            ]);
    }
}
