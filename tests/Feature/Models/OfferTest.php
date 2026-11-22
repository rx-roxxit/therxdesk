<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\Offer;
use App\Models\Plan;

test('an offer that started and has no end is available', function () {
    $offer = Offer::factory()->create();

    expect(Offer::available()->pluck('id')->all())->toBe([$offer->id]);
});

test('an offer that has not started is not available', function () {
    Offer::factory()->upcoming()->create();

    expect(Offer::available()->count())->toBe(0);
});

test('an offer past its end is not available', function () {
    Offer::factory()->expired()->create();

    expect(Offer::available()->count())->toBe(0);
});

test('an offer with an end in the future is available', function () {
    Offer::factory()->create(['ends_at' => now()->addDay()]);

    expect(Offer::available()->count())->toBe(1);
});

test('a full offer is not available', function () {
    $offer = Offer::factory()->limitedTo(1)->create();
    $offer->accounts()->attach(Account::factory()->create(), ['claimed_at' => now(), 'redeemed_at' => now()]);

    expect(Offer::available()->count())->toBe(0);
});

test('claims do not use up places', function () {
    $offer = Offer::factory()->limitedTo(1)->create();
    $offer->accounts()->attach(Account::factory()->create(), ['claimed_at' => now()]);

    expect(Offer::available()->count())->toBe(1);
});

test('a general offer needs no claim and the latest one to start wins', function () {
    $account = Account::factory()->create();
    $earlier = Offer::factory()->general()->create(['starts_at' => now()->subDays(2)]);
    $later = Offer::factory()->general()->create(['starts_at' => now()->subDay()]);

    expect($account->currentOffer()?->id)->toBe($later->id)
        ->and($earlier->id)->not->toBe($account->currentOffer()?->id);
});

test('a link offer the account claimed beats the general offer', function () {
    $account = Account::factory()->create();
    $link = Offer::factory()->link()->create();
    Offer::factory()->general()->create();
    $account->offers()->attach($link, ['claimed_at' => now()]);

    expect($account->currentOffer()?->id)->toBe($link->id);
});

test('current offer ignores expired link claims and uses the general offer', function () {
    $account = Account::factory()->create();
    $general = Offer::factory()->general()->create();
    $account->offers()->attach(Offer::factory()->link()->expired()->create(), ['claimed_at' => now()]);

    expect($account->currentOffer()?->id)->toBe($general->id);
});

test('current offer is empty without a link claim or a general offer', function () {
    Offer::factory()->link()->create();

    expect(Account::factory()->create()->currentOffer())->toBeNull();
});

test('deleting an account removes its offer claims', function () {
    $account = Account::factory()->create();
    $offer = Offer::factory()->create();
    $account->offers()->attach($offer, ['claimed_at' => now()]);

    $account->delete();

    $this->assertDatabaseMissing('account_offer', ['offer_id' => $offer->id]);
});

test('an offer the account redeemed is no longer available to it, only to others', function () {
    $offer = Offer::factory()->link()->create();
    $account = Account::factory()->create();
    $account->offers()->attach($offer, ['claimed_at' => now(), 'redeemed_at' => now()]);

    expect(Offer::availableTo($account)->whereKey($offer->id)->exists())->toBeFalse()
        ->and(Offer::availableTo(Account::factory()->create())->whereKey($offer->id)->exists())->toBeTrue();
});

test('a general offer the account redeemed is skipped for that account', function () {
    $account = Account::factory()->create();
    $first = Offer::factory()->general()->create(['starts_at' => now()->subDay()]);
    $second = Offer::factory()->general()->create(['starts_at' => now()->subDays(2)]);
    $account->offers()->attach($first, ['claimed_at' => now(), 'redeemed_at' => now()]);

    expect($account->currentOffer()?->id)->toBe($second->id);
});

test('a general offer is reachable without a claim but not by its link', function () {
    $offer = Offer::factory()->general()->create(['slug' => 'general-offer']);

    expect(Offer::findAvailable('general-offer'))->toBeNull()
        ->and(Account::factory()->create()->currentOffer()?->id)->toBe($offer->id);
});

test('a link offer is found by its exact slug', function () {
    $offer = Offer::factory()->link()->create(['slug' => 'spring-sale']);

    expect(Offer::findAvailable('spring-sale')?->id)->toBe($offer->id)
        ->and(Offer::findAvailable('SPRING-SALE'))->toBeNull();
});

test('an offer covers only its plans and intervals', function () {
    $monthly = Plan::factory()->create();
    $yearly = Plan::factory()->create();
    $offer = Offer::factory()->covering([$monthly], [Interval::Monthly])->make();

    expect($offer->covers($monthly, Interval::Monthly))->toBeTrue()
        ->and($offer->covers($monthly, Interval::Yearly))->toBeFalse()
        ->and($offer->covers($yearly, Interval::Monthly))->toBeFalse();
});

test('an ended offer is read-only', function () {
    expect(Offer::factory()->expired()->make()->hasEnded())->toBeTrue()
        ->and(Offer::factory()->make()->hasEnded())->toBeFalse()
        ->and(Offer::factory()->make(['ends_at' => now()->addDay()])->hasEnded())->toBeFalse();
});

test('a trial needs at least one day', function (?int $days, bool $hasTrial) {
    $offer = Offer::factory()->make(['trial_days' => $days]);

    expect($offer->hasTrial())->toBe($hasTrial);
})->with([
    'no trial' => [null, false],
    'zero days' => [0, false],
    'seven days' => [7, true],
]);
