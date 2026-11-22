<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Enums\User\Locale;

/**
 * The language Stripe Checkout and the customer's `preferred_locales` use for
 * an app locale. Stripe supports more locales than The RX Desk, and four app
 * locales it does not localize: Arabic and Ukrainian fall back to English,
 * Chinese uses Stripe's `zh`, and the rest share their code with Stripe.
 *
 * The supported list is Stripe's Checkout locale list (checked 2026-11-22):
 * https://docs.stripe.com/api/checkout/sessions/create#create_checkout_session-locale
 */
final class StripeLocale
{
    /**
     * @var list<string>
     */
    public const array SUPPORTED = [
        'auto',
        'bg',
        'cs',
        'da',
        'de',
        'el',
        'en',
        'en-GB',
        'es',
        'es-419',
        'et',
        'fi',
        'fil',
        'fr',
        'fr-CA',
        'hr',
        'hu',
        'id',
        'it',
        'ja',
        'ko',
        'lt',
        'lv',
        'ms',
        'mt',
        'nb',
        'nl',
        'pl',
        'pt',
        'pt-BR',
        'ro',
        'ru',
        'sk',
        'sl',
        'sv',
        'th',
        'tr',
        'vi',
        'zh',
        'zh-HK',
        'zh-TW',
    ];

    /**
     * Never `auto`: the Checkout page speaks the customer's language whatever
     * their browser says.
     */
    public static function for(Locale $locale): string
    {
        return match ($locale) {
            Locale::Arabic, Locale::Ukrainian => 'en',
            Locale::Chinese => 'zh',
            default => $locale->value,
        };
    }
}
