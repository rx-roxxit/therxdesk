<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Actions\Billing\RefundAndCancel;
use App\Enums\Billing\RefundDenial;
use App\Models\Account;
use Carbon\CarbonImmutable;
use Laravel\Cashier\Cashier;
use Stripe\Invoice as StripeInvoice;

/**
 * Whether an Account may be refunded and cancelled, and the charge that would
 * be refunded. One decision shared by the admin detail read and the action, so
 * the screen never offers a refund the endpoint would refuse.
 *
 * A Stripe failure is not a denial here: it propagates, and each caller turns it
 * into its own answer (a 502 for the action, `stripe_unavailable` for the read).
 */
final class RefundEligibility
{
    /**
     * @return array{
     *     denial: RefundDenial|null,
     *     invoice: StripeInvoice|null,
     *     amount_paid: int,
     *     currency: string|null,
     *     paid_at: CarbonImmutable|null,
     *     payment: array{payment_intent: string}|array{charge: string}|null
     * }
     */
    public static function for(Account $account): array
    {
        if (! ThaiPurchaseDocument::applies($account)) {
            return self::denied(RefundDenial::NotThai);
        }

        if ($account->subscription(Account::SUBSCRIPTION_NAME) === null) {
            return self::denied(RefundDenial::NoSubscription);
        }

        if (blank($account->stripe_id)) {
            return self::denied(RefundDenial::NoStripeCustomer);
        }

        $invoice = self::latestPaidInvoice($account);

        if ($invoice === null) {
            return self::denied(RefundDenial::NoRecentCharge);
        }

        $payment = self::paymentReference(self::withPayments($invoice));

        if ($payment === null) {
            return self::denied(RefundDenial::NoRefundablePayment, $invoice);
        }

        return [
            'denial' => null,
            'invoice' => $invoice,
            'amount_paid' => (int) $invoice->amount_paid,
            'currency' => $invoice->currency,
            'paid_at' => self::paidAt($invoice),
            'payment' => $payment,
        ];
    }

    /**
     * @return array{denial: RefundDenial, invoice: StripeInvoice|null, amount_paid: int, currency: string|null, paid_at: CarbonImmutable|null, payment: null}
     */
    private static function denied(RefundDenial $denial, ?StripeInvoice $invoice = null): array
    {
        return [
            'denial' => $denial,
            'invoice' => $invoice,
            'amount_paid' => $invoice === null ? 0 : (int) $invoice->amount_paid,
            'currency' => $invoice?->currency,
            'paid_at' => $invoice === null ? null : self::paidAt($invoice),
            'payment' => null,
        ];
    }

    /**
     * The newest paid invoice whose charge landed within the window.
     */
    private static function latestPaidInvoice(Account $account): ?StripeInvoice
    {
        $invoices = Cashier::stripe()->invoices->all([
            'customer' => $account->stripe_id,
            'status' => 'paid',
            'limit' => 100,
        ]);

        $cutoff = now()->subDays(RefundAndCancel::WINDOW_DAYS);
        $latest = null;
        $latestAt = null;

        foreach ($invoices->data as $invoice) {
            if ((int) $invoice->amount_paid <= 0) {
                continue;
            }

            $paidAt = self::paidAt($invoice);

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

    /**
     * The invoice with its payments expanded: a newer Stripe API drops
     * `payment_intent` and `charge` from the invoice itself.
     */
    private static function withPayments(StripeInvoice $invoice): StripeInvoice
    {
        return Cashier::stripe()->invoices->retrieve($invoice->id, ['expand' => ['payments']]);
    }

    private static function paidAt(StripeInvoice $invoice): ?CarbonImmutable
    {
        $attributes = $invoice->toArray();

        return StripeValue::timestamp(data_get($attributes, 'status_transitions.paid_at'))
            ?? StripeValue::timestamp(data_get($attributes, 'created'));
    }

    /**
     * The original payment method of a paid invoice, as the parameter a Stripe
     * refund takes: its payment intent, else its charge.
     *
     * @return array{payment_intent: string}|array{charge: string}|null
     */
    private static function paymentReference(StripeInvoice $invoice): ?array
    {
        $attributes = $invoice->toArray();

        foreach (data_get($attributes, 'payments.data', []) as $entry) {
            $reference = self::reference(data_get($entry, 'payment', []));

            if ($reference !== null) {
                return $reference;
            }
        }

        return self::reference($attributes);
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{payment_intent: string}|array{charge: string}|null
     */
    private static function reference(array $payment): ?array
    {
        $paymentIntent = self::stripeId(data_get($payment, 'payment_intent'));

        if ($paymentIntent !== null) {
            return ['payment_intent' => $paymentIntent];
        }

        $charge = self::stripeId(data_get($payment, 'charge'));

        return $charge === null ? null : ['charge' => $charge];
    }

    private static function stripeId(mixed $value): ?string
    {
        if (is_string($value) && filled($value)) {
            return $value;
        }

        $id = data_get($value, 'id');

        return is_string($id) && filled($id) ? $id : null;
    }
}
