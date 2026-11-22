<?php

declare(strict_types=1);

use App\Enums\User\Locale;
use App\Support\Billing\StripeLocale;

test('every app locale maps to a supported Stripe locale and never auto', function (Locale $locale) {
    $mapped = StripeLocale::for($locale);

    expect(StripeLocale::SUPPORTED)->toContain($mapped)
        ->and($mapped)->not->toBe('auto');
})->with(Locale::cases());

test('the locales Stripe cannot localize fall back to English', function (Locale $locale) {
    expect(StripeLocale::for($locale))->toBe('en');
})->with([Locale::Arabic, Locale::Ukrainian]);

test('Chinese uses the language without a region', function () {
    expect(StripeLocale::for(Locale::Chinese))->toBe('zh');
});

test('Portuguese Brazil keeps its region', function () {
    expect(StripeLocale::for(Locale::PortugueseBrazil))->toBe('pt-BR');
});
