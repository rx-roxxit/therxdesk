<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Models\Account;
use ArrayAccess;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Throwable;

/**
 * Reads the fingerprint of the card a Stripe subscription will charge, so a
 * repeat card cannot start another Trial. Stripe sends the default payment
 * method as an id or an expanded object depending on the webhook.
 */
final class CardFingerprint
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function forSubscription(Account $account, array $payload): ?string
    {
        $defaultPaymentMethod = data_get($payload, 'data.object.default_payment_method');

        $fingerprint = self::fromPaymentMethod($defaultPaymentMethod);

        if (filled($fingerprint)) {
            return $fingerprint;
        }

        if (is_string($defaultPaymentMethod) && filled($defaultPaymentMethod)) {
            $fingerprint = self::fromRetrievedPaymentMethod($defaultPaymentMethod);

            if (filled($fingerprint)) {
                return $fingerprint;
            }
        }

        $fingerprint = self::fromCustomerDefault($account);

        if (filled($fingerprint)) {
            return $fingerprint;
        }

        return self::fromNewestCard($account);
    }

    private static function fromPaymentMethod(mixed $paymentMethod): ?string
    {
        if (! is_array($paymentMethod) && ! $paymentMethod instanceof ArrayAccess) {
            return null;
        }

        $fingerprint = data_get($paymentMethod, 'card.fingerprint');

        return is_string($fingerprint) && filled($fingerprint) ? $fingerprint : null;
    }

    private static function fromRetrievedPaymentMethod(string $paymentMethodId): ?string
    {
        try {
            return self::fromPaymentMethod(
                Cashier::stripe()->paymentMethods->retrieve($paymentMethodId)
            );
        } catch (Throwable $e) {
            Log::warning("Card fingerprint: payment method {$paymentMethodId} could not be read: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return null;
        }
    }

    private static function fromCustomerDefault(Account $account): ?string
    {
        if (blank($account->stripe_id)) {
            return null;
        }

        try {
            $customer = Cashier::stripe()->customers->retrieve($account->stripe_id, [
                'expand' => ['invoice_settings.default_payment_method'],
            ]);
        } catch (Throwable $e) {
            Log::warning("Card fingerprint: customer {$account->stripe_id} could not be read: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return null;
        }

        $defaultPaymentMethod = data_get($customer, 'invoice_settings.default_payment_method');

        $fingerprint = self::fromPaymentMethod($defaultPaymentMethod);

        if (filled($fingerprint)) {
            return $fingerprint;
        }

        if (is_string($defaultPaymentMethod) && filled($defaultPaymentMethod)) {
            return self::fromRetrievedPaymentMethod($defaultPaymentMethod);
        }

        return null;
    }

    private static function fromNewestCard(Account $account): ?string
    {
        if (blank($account->stripe_id)) {
            return null;
        }

        try {
            $card = $account->paymentMethods('card')->first();
        } catch (Throwable $e) {
            Log::warning("Card fingerprint: cards of customer {$account->stripe_id} could not be read: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return null;
        }

        if ($card === null) {
            return null;
        }

        return self::fromPaymentMethod($card->asStripePaymentMethod());
    }
}
