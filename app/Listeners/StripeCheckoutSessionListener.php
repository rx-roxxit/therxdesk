<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\Billing\SendThaiPurchaseDocument;
use App\Models\Account;
use App\Support\Billing\BlockedCountries;
use App\Support\Billing\StripeValue;
use App\Support\Billing\ThaiPurchaseDocument;
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
 * A completed Checkout billed to a country The RX Desk does not sell in (the
 * EU/UK block) is refunded when it charged and cancelled at once. A normal
 * Checkout is still in its Trial and nothing is charged. The register form
 * already refuses a blocked country; this guards a false or changed billing
 * address at Stripe.
 *
 * The same event records the checkout facts the refund action reads (billing
 * country, billing name, purchase date) for every selling-mode checkout, and
 * mails the Thai purchase document when the billing country is Thailand.
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
            $stripeCustomerId = data_get($event->payload, 'data.object.customer');

            if (! is_string($stripeCustomerId) || blank($stripeCustomerId)) {
                return;
            }

            /** @var Account|null $account */
            $account = Cashier::findBillable($stripeCustomerId);

            if (! $account) {
                return;
            }

            $this->recordCheckoutFacts($account, $event->payload);
            $this->sendThaiPurchaseDocument($account, $event->payload);

            $country = data_get($event->payload, 'data.object.customer_details.address.country');

            if (! BlockedCountries::isBlocked(is_string($country) ? $country : null)) {
                return;
            }

            $this->recordBlock($account, (string) $country, data_get($event->payload, 'data.object.payment_status'));

            $this->refundChargedSession($account, $event->payload);

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
     * The facts the Thai purchase document and the refund action read, and the
     * Checkout Session they belong to so Processing waits for this checkout
     * rather than an older one's. A newer checkout replaces an older one; an
     * older or repeated delivery never overwrites facts it does not postdate.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordCheckoutFacts(Account $account, array $payload): void
    {
        $completedAt = StripeValue::timestamp(data_get($payload, 'created')) ?? now();

        if (filled($account->checkout_completed_at) && $account->checkout_completed_at->greaterThan($completedAt)) {
            return;
        }

        $country = data_get($payload, 'data.object.customer_details.address.country');
        $name = data_get($payload, 'data.object.customer_details.name');
        $sessionId = data_get($payload, 'data.object.id');

        $attributes = ['checkout_completed_at' => $completedAt];

        if (is_string($sessionId) && filled($sessionId)) {
            $attributes['checkout_session_id'] = $sessionId;
        }

        if (is_string($country) && filled($country)) {
            $attributes['billing_country'] = Str::upper(trim($country));
        }

        if (is_string($name) && filled($name)) {
            $attributes['billing_name'] = $name;
        }

        $account->forceFill($attributes)->save();
    }

    /**
     * Queues the order-confirmation email a Thai checkout gets. It is sent a
     * few seconds later so the subscription webhook has settled the Plan, its
     * price and the Trial's dates; the job claims the Session so it sends once.
     *
     * @param  array<string, mixed>  $payload
     */
    private function sendThaiPurchaseDocument(Account $account, array $payload): void
    {
        if ($account->billing_country !== ThaiPurchaseDocument::COUNTRY) {
            return;
        }

        $sessionId = data_get($payload, 'data.object.id');

        if (! is_string($sessionId) || blank($sessionId)) {
            return;
        }

        SendThaiPurchaseDocument::dispatch($account->id, $sessionId)
            ->delay(now()->addSeconds(20));
    }

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
     * A blocked-country Checkout that charged (a repeat card, which skips its
     * Trial) gets the money back before the Subscription is cancelled. A Trial
     * that charged nothing is left alone. The session is the idempotency key,
     * so a redelivered event never refunds twice.
     *
     * @param  array<string, mixed>  $payload
     */
    private function refundChargedSession(Account $account, array $payload): void
    {
        if (data_get($payload, 'data.object.payment_status') !== 'paid') {
            return;
        }

        $paymentIntent = data_get($payload, 'data.object.payment_intent');

        if (! is_string($paymentIntent) || blank($paymentIntent)) {
            Log::warning('A blocked-country Checkout was paid without a payment intent to refund.', [
                'account_id' => $account->id,
                'session_id' => data_get($payload, 'data.object.id'),
            ]);

            return;
        }

        $sessionId = data_get($payload, 'data.object.id');
        $options = [];

        if (is_string($sessionId) && filled($sessionId)) {
            $options['idempotency_key'] = "rxdesk-blocked-country-refund-{$sessionId}";
        }

        Cashier::stripe()->refunds->create(
            [
                'payment_intent' => $paymentIntent,
                'reason' => 'requested_by_customer',
            ],
            $options,
        );
    }

    /**
     * Cancels at Stripe, then ends the local subscription so access stops
     * before the deleted webhook arrives.
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
