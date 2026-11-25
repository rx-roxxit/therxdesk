<?php

declare(strict_types=1);

namespace App\Support\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Reads the raw values of a Stripe payload, null when a value is missing or
 * not of the type Stripe sends.
 */
final class StripeValue
{
    /**
     * Stripe sends amounts in cents; TryPost charges in BRL, USD and EUR only,
     * all with two decimals.
     */
    public static function amount(mixed $cents): ?float
    {
        return is_int($cents) ? $cents / 100 : null;
    }

    public static function currency(mixed $currency): ?string
    {
        return is_string($currency) && filled($currency) ? Str::upper($currency) : null;
    }

    public static function timestamp(mixed $seconds): ?CarbonImmutable
    {
        return is_numeric($seconds) ? CarbonImmutable::createFromTimestampUTC((int) $seconds) : null;
    }

    /**
     * The subscription an invoice belongs to. Stripe's newer API moves it from
     * the invoice's `subscription` field to `parent.subscription_details`, and
     * the old field is sent as null, so both places are read.
     *
     * @param  array<string, mixed>  $invoice
     */
    public static function invoiceSubscriptionId(array $invoice): ?string
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
