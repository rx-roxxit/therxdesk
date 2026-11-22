<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Account;
use App\Support\Billing\StripeValue;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use Stripe\Event as StripeEvent;
use Throwable;

/**
 * Records the first invoice that was actually paid, before Cashier's own
 * listeners. It is the durable proof that an Account was charged, kept even
 * when the subscription webhook arrives late or is redelivered.
 */
class StripeInvoiceListener
{
    public function handle(WebhookReceived $event): void
    {
        try {
            if (data_get($event->payload, 'type') !== StripeEvent::TYPE_INVOICE_PAYMENT_SUCCEEDED) {
                return;
            }

            $stripeCustomerId = data_get($event->payload, 'data.object.customer');

            if (blank($stripeCustomerId)) {
                return;
            }

            /** @var Account|null $account */
            $account = Cashier::findBillable($stripeCustomerId);

            if (! $account) {
                return;
            }

            if (data_get($event->payload, 'data.object.status') !== 'paid') {
                return;
            }

            $amountPaid = data_get($event->payload, 'data.object.amount_paid');

            if (! is_numeric($amountPaid) || (int) $amountPaid <= 0) {
                return;
            }

            $this->recordPaidWithoutTrial($account, $event->payload);

            if (filled($account->first_invoice_id)) {
                return;
            }

            $invoiceId = data_get($event->payload, 'data.object.id');

            if (! is_string($invoiceId) || blank($invoiceId)) {
                return;
            }

            $paidAt = StripeValue::timestamp(data_get($event->payload, 'data.object.status_transitions.paid_at')) ?? now();

            $account->forceFill([
                'first_invoice_id' => $invoiceId,
                'first_paid_at' => $account->first_paid_at ?? $paidAt,
            ])->save();
        } catch (Throwable $e) {
            Log::error("Stripe invoice listener error: {$e->getMessage()}", [
                'exception' => $e,
                'payload' => $event->payload,
            ]);
        }
    }

    /**
     * The repeat card whose Trial was skipped was actually charged: the
     * subscription's own active update cannot tell that charge from the
     * `active` moment before Stripe attempts it, so the paid invoice does.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordPaidWithoutTrial(Account $account, array $payload): void
    {
        $stripeSubscriptionId = data_get($payload, 'data.object.subscription');

        if (! is_string($stripeSubscriptionId) || blank($stripeSubscriptionId)) {
            return;
        }

        $subscription = $account->subscriptions()
            ->where('stripe_id', $stripeSubscriptionId)
            ->first();

        if ($subscription === null) {
            return;
        }

        if (blank($subscription->trial_skipped_at) || filled($subscription->paid_without_trial_at)) {
            return;
        }

        $subscription->forceFill(['paid_without_trial_at' => now()])->save();
    }
}
