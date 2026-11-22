<?php

declare(strict_types=1);

use App\Support\Billing\BlockedCountries;
use App\Support\Countries;

test('every blocked code is blocked, in any case', function (string $country) {
    expect(BlockedCountries::isBlocked($country))->toBeTrue();
})->with(BlockedCountries::CODES);

test('the blocked set is the EU27, the EEA, the UK and the Crown dependencies', function () {
    expect(BlockedCountries::CODES)->toHaveCount(34)
        ->and(BlockedCountries::CODES)->toContain('DE', 'FR', 'NO', 'IS', 'LI', 'GB', 'JE', 'GG', 'IM');
});

test('an allowed country is not blocked', function (string $country) {
    expect(BlockedCountries::isBlocked($country))->toBeFalse();
})->with(['CH', 'TH', 'US', 'AU', 'JP', 'ch', 'th']);

test('a missing or blank country is not blocked', function (?string $country) {
    expect(BlockedCountries::isBlocked($country))->toBeFalse();
})->with([null, '', '  ']);

test('the ISO list holds every officially assigned code once, uppercase and two letters', function () {
    expect(Countries::ISO)->toHaveCount(249)
        ->and(Countries::ISO)->toBe(array_values(array_unique(Countries::ISO)));

    foreach (Countries::ISO as $code) {
        expect($code)->toMatch('/^[A-Z]{2}$/');
    }
});

test('the ISO list is the source the blocked set is drawn from', function () {
    expect(array_diff(BlockedCountries::CODES, Countries::ISO))->toBe([]);
});

test('the ISO list carries the countries the register form must accept', function () {
    expect(Countries::ISO)->toContain('TH', 'CH', 'US', 'GB', 'DE');
});

test('isIso accepts an assigned code in any case and refuses anything else', function () {
    expect(Countries::isIso('TH'))->toBeTrue()
        ->and(Countries::isIso('th'))->toBeTrue()
        ->and(Countries::isIso('XX'))->toBeFalse()
        ->and(Countries::isIso('THA'))->toBeFalse()
        ->and(Countries::isIso(''))->toBeFalse();
});
