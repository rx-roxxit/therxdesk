<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Account\SyncDeletionClock;
use App\Actions\Audit\RecordAuditEntry;
use App\Enums\Billing\CancellationType;
use App\Enums\Billing\RefundDenial;
use App\Exceptions\Billing\RefundFailed;
use App\Models\Account;
use App\Models\Subscription;
use App\Support\Billing\RefundEligibility;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund as StripeRefund;
use Stripe\Subscription as StripeSubscription;

/**
 * The admin app's Refund and cancel action for a Thai buyer: the latest paid
 * charge within the seven-day window is refunded in full through the original
 * payment method, the Subscription is cancelled at once so access ends, the
 * cancellation is recorded as an admin one (a later webhook never replaces
 * it), and the whole action is audited.
 *
 * The checks live in RefundEligibility, the same decision the customer detail
 * read exposes. It refuses (422) a non-Thai buyer, an Account with no
 * Subscription or no paid charge in the window, and a charge with nothing
 * refundable. A Stripe failure is a 502 that exposes no detail.
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
        try {
            $eligibility = RefundEligibility::for($account);

            if ($eligibility['denial'] !== null) {
                throw ValidationException::withMessages([
                    'account' => $eligibility['denial']->message(),
                ]);
            }

            $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);

            if (! $subscription instanceof Subscription) {
                throw ValidationException::withMessages([
                    'account' => RefundDenial::NoSubscription->message(),
                ]);
            }

            $before = $this->snapshot($subscription);

            $refund = $this->refund($eligibility['payment'], $eligibility['amount_paid']);
            $this->cancelAtStripe($subscription);
        } catch (ApiErrorException $exception) {
            throw RefundFailed::because($exception);
        }

        $invoiceId = $eligibility['invoice']->id;
        $amountPaid = $eligibility['amount_paid'];
        $cancelledAt = now();

        $this->endLocalSubscription($subscription, $cancelledAt);

        RecordAuditEntry::execute($actor, 'account.refund_and_cancel', $account, $before, [
            'subscription_id' => $subscription->stripe_id,
            'status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => $cancelledAt->toIso8601String(),
            'cancellation_type' => CancellationType::Admin->value,
            'invoice_id' => $invoiceId,
            'amount_paid' => $amountPaid,
            'currency' => $eligibility['currency'],
            'refund_id' => $refund->id,
        ]);

        $account = $account->fresh();
        SyncDeletionClock::execute($account);

        return [
            'invoice_id' => $invoiceId,
            'amount_paid' => $amountPaid,
            'currency' => $eligibility['currency'],
            'refund_id' => $refund->id,
            'subscription_id' => $subscription->stripe_id,
            'status' => StripeSubscription::STATUS_CANCELED,
            'ends_at' => $cancelledAt->toIso8601String(),
            'has_app_access' => $account->hasAppAccess(),
        ];
    }

    /**
     * @param  array{payment_intent: string}|array{charge: string}  $payment
     */
    private function refund(array $payment, int $amountPaid): StripeRefund
    {
        return Cashier::stripe()->refunds->create([
            ...$payment,
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
}
