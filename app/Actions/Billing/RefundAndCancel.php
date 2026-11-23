<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Account\SyncDeletionClock;
use App\Actions\Audit\RecordAuditEntry;
use App\Enums\Billing\CancellationType;
use App\Exceptions\Billing\RefundFailed;
use App\Models\Account;
use App\Models\Subscription;
use App\Support\Billing\StripeValue;
use App\Support\Billing\ThaiPurchaseDocument;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice as StripeInvoice;
use Stripe\Refund as StripeRefund;
use Stripe\Subscription as StripeSubscription;

/**
 * The admin app's Refund and cancel action for a Thai buyer: the latest paid
 * charge within the seven-day window is refunded in full through the original
 * payment method, the Subscription is cancelled at once so access ends, the
 * cancellation is recorded as an admin one (a later webhook never replaces
 * it), and the whole action is audited.
 *
 * It refuses (422) a non-Thai buyer, an Account with no Subscription or no paid
 * charge in the window, and a charge with nothing refundable. A Stripe failure
 * is a 502 that exposes no detail.
 */
class RefundAndCancel
{
    public const int WINDOW_DAYS = 7;

    /**
     * @return array{
     *     invoice_id: string,
     *     amount_paid: int,
     *     currency: string|null,
     *     refund_id: string,
     *     subscription_id: string,
     *     status: string,
     *     ends_at: string,
     *     has_app_access: bool
     * }
     */
    public function execute(Account $account, string $actor): array
    {
        if (! ThaiPurchaseDocument::applies($account)) {
            throw ValidationException::withMessages([
                'account' => 'Refund and cancel is only available for a Thai buyer.',
            ]);
        }

        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'account' => 'This Account has no subscription to cancel.',
            ]);
        }

        if (blank($account->stripe_id)) {
            throw ValidationException::withMessages([
                'account' => 'This Account has no Stripe customer.',
            ]);
        }

        $before = $this->snapshot($subscription);

        try {
            $invoice = $this->latestPaidInvoice($account);

            if ($invoice === null) {
                throw ValidationException::withMessages([
                    'account' => 'This Account has no paid charge in the last seven days.',
                ]);
            }

            $amountPaid = (int) $invoice->amount_paid;

            $refund = $this->refund($invoice, $amountPaid);
            $this->cancelAtStripe($subscription);
        } catch (ApiErrorException $exception) {
            throw RefundFailed::because($exception);
        }

        $cancelledAt = now();

        $this->endLocalSubscription($subscription, $cancelledAt);

        RecordAuditEntry::execute($actor, 'account.refund_and_cancel', $account, $before, [
            'subscription_id' => $subscription->stripe_id,
            'status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => $cancelledAt->toIso8601String(),
            'cancellation_type' => CancellationType::Admin->value,
            'invoice_id' => $invoice->id,
            'amount_paid' => $amountPaid,
            'currency' => $invoice->currency,
            'refund_id' => $refund->id,
        ]);

        $account = $account->fresh();
        SyncDeletionClock::execute($account);

        return [
            'invoice_id' => $invoice->id,
            'amount_paid' => $amountPaid,
            'currency' => $invoice->currency,
            'refund_id' => $refund->id,
            'subscription_id' => $subscription->stripe_id,
            'status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => $cancelledAt->toIso8601String(),
            'has_app_access' => $account->hasAppAccess(),
        ];
    }

    /**
     * The newest paid invoice whose charge landed within the window.
     */
    private function latestPaidInvoice(Account $account): ?StripeInvoice
    {
        $invoices = Cashier::stripe()->invoices->all([
            'customer' => $account->stripe_id,
            'status' => 'paid',
            'limit' => 100,
        ]);

        $cutoff = now()->subDays(self::WINDOW_DAYS);
        $latest = null;
        $latestAt = null;

        foreach ($invoices->data as $invoice) {
            if ((int) $invoice->amount_paid <= 0) {
                continue;
            }

            $paidAt = $this->paidAt($invoice);

            if ($paidAt === null || $paidAt->lessThan($cutoff)) {
                continue;
            }

            if ($latestAt === null || $paidAt->greaterThan($latestAt)) {
                $latest = $invoice;
                $latestAt = $paidAt;
            }
        }

        return $latest;
    }

    private function paidAt(StripeInvoice $invoice): ?CarbonInterface
    {
        $attributes = $invoice->toArray();

        return StripeValue::timestamp(data_get($attributes, 'status_transitions.paid_at'))
            ?? StripeValue::timestamp(data_get($attributes, 'created'));
    }

    private function refund(StripeInvoice $invoice, int $amountPaid): StripeRefund
    {
        $paymentIntent = $this->stripeId($invoice->payment_intent);

        if ($paymentIntent !== null) {
            return Cashier::stripe()->refunds->create([
                'payment_intent' => $paymentIntent,
                'amount' => $amountPaid,
            ]);
        }

        $charge = $this->stripeId($invoice->charge);

        if ($charge === null) {
            throw ValidationException::withMessages([
                'account' => 'This charge has no refundable payment.',
            ]);
        }

        return Cashier::stripe()->refunds->create([
            'charge' => $charge,
            'amount' => $amountPaid,
        ]);
    }

    private function cancelAtStripe(Subscription $subscription): void
    {
        $stripeSubscription = Cashier::stripe()->subscriptions->retrieve($subscription->stripe_id);

        if ($stripeSubscription->status === StripeSubscription::STATUS_CANCELED) {
            return;
        }

        Cashier::stripe()->subscriptions->cancel($subscription->stripe_id);
    }

    private function endLocalSubscription(Subscription $subscription, CarbonInterface $cancelledAt): void
    {
        $subscription->forceFill([
            'stripe_status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => $cancelledAt,
        ])->save();

        $subscription->recordCancellation(CancellationType::Admin, 'ended by the System admin', $cancelledAt);
    }

    /**
     * @return array{subscription_id: string, status: string, ends_at: string|null, cancellation_type: string|null}
     */
    private function snapshot(Subscription $subscription): array
    {
        return [
            'subscription_id' => $subscription->stripe_id,
            'status' => $subscription->stripe_status,
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'cancellation_type' => $subscription->cancellation_type?->value,
        ];
    }

    private function stripeId(mixed $value): ?string
    {
        if (is_string($value) && filled($value)) {
            return $value;
        }

        $id = data_get($value, 'id');

        return is_string($id) && filled($id) ? $id : null;
    }
}
