<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Enums\User\Locale;
use App\Models\Offer;
use App\Support\Billing\OfferCopy;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;

const OFFER_LINE_KEYS = [
    'once_monthly',
    'once_yearly',
    'repeating_monthly',
    'repeating_yearly',
    'forever',
    'forever_until',
    'trial',
];

test('every offer template exists in every locale', function () {
    $missing = collect(Locale::cases())->flatMap(fn (Locale $locale): array => collect(OFFER_LINE_KEYS)
        ->reject(fn (string $key): bool => Lang::has("rxdesk.offer.line.{$key}", $locale->value, false))
        ->map(fn (string $key): string => "{$locale->value}: rxdesk.offer.line.{$key}")
        ->all());

    expect($missing->values()->all())->toBe([]);
});

test('no offer has a per-slug lang key any more', function () {
    expect(Lang::has('offers.bf26.name', 'en', false))->toBeFalse()
        ->and(Lang::has('offers.ft7dnc.terms', 'en', false))->toBeFalse();
});

test('the templates render every duration in every locale without leaving a placeholder', function (string $locale) {
    App::setLocale($locale);

    $offers = [
        Offer::factory()->percent(20)->repeating(3)->make(),
        Offer::factory()->amount(500)->once()->make(),
        Offer::factory()->percent(35)->forever()->make(['ends_at' => now()->addMonth()]),
        Offer::factory()->trial(14)->make(),
    ];

    foreach ($offers as $offer) {
        $line = OfferCopy::line($offer, Interval::Monthly);

        expect($line)->not->toBe('')
            ->not->toContain('rxdesk.')
            ->not->toContain(':discount')
            ->not->toContain(':months')
            ->not->toContain(':days')
            ->not->toContain(':date');
    }
})->with(Locale::values());

test('an English repeating offer reads like the spec example and pluralizes its months', function () {
    App::setLocale('en');

    $threeMonths = Offer::factory()->percent(20)->repeating(3)->make();
    $oneMonth = Offer::factory()->percent(20)->repeating(1)->make();

    expect(OfferCopy::line($threeMonths, Interval::Monthly))->toBe('20% off your first 3 months')
        ->and(OfferCopy::line($oneMonth, Interval::Monthly))->toBe('20% off your first month');
});

test('the yearly lines say first year, never first 3 months', function () {
    App::setLocale('en');

    $offer = Offer::factory()->percent(20)->repeating(3)->make();

    expect(OfferCopy::line($offer, Interval::Monthly))->toBe('20% off your first 3 months')
        ->and(OfferCopy::line($offer, Interval::Yearly))->toBe('20% off your first year');
});

test('a forever offer with an end carries the date', function () {
    App::setLocale('en');

    $endsAt = now()->addDays(10)->startOfDay();
    $offer = Offer::factory()->percent(35)->forever()->make(['ends_at' => $endsAt]);

    expect(OfferCopy::line($offer, Interval::Monthly))
        ->toBe('35% off for as long as you stay subscribed, until '.OfferCopy::date($endsAt));
});

test('an amount offer writes the amount in the reader currency', function () {
    App::setLocale('en');

    $offer = Offer::factory()->amount(500)->once()->make();

    expect(OfferCopy::line($offer, Interval::Monthly))->toBe('$5.00 off your first month');
});

test('a trial-only offer writes its days and has no discount label', function () {
    App::setLocale('en');

    $offer = Offer::factory()->trial(14)->make();

    expect(OfferCopy::discountLabel($offer))->toBeNull()
        ->and(OfferCopy::line($offer, Interval::Monthly))->toBe('14-day free trial');
});
