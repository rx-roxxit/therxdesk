<?php

declare(strict_types=1);

use App\Enums\User\Locale;
use App\Models\Account;
use App\Models\CatalogSetting;
use App\Models\Offer;
use App\Support\Billing\ConfigureSubscriptionCheckout;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\SubscriptionBuilder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->account = Account::factory()->create();
});

function givePriorSubscription(Account $account, string $status = 'canceled', bool $withTrial = false): void
{
    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => $status,
        'stripe_price' => 'price_monthly_test',
        'trial_ends_at' => $withTrial ? now()->subMonth() : null,
    ]);
    $account->unsetRelation('subscriptions');
}

function trialExpiresAt(SubscriptionBuilder $subscription): ?Carbon
{
    $property = (new ReflectionClass($subscription))->getProperty('trialExpires');

    return $property->getValue($subscription);
}

function claimOfferFor(Account $account, Offer $offer): Offer
{
    $account->offers()->attach($offer, ['claimed_at' => now()]);

    return $offer;
}

function setCatalogTrial(int $days): void
{
    CatalogSetting::factory()->create(['id' => 1, 'trial_days' => $days]);
}

function configureCheckout(Account $account, ?Offer $offer = null): SubscriptionBuilder
{
    $subscription = $account->newSubscription(Account::SUBSCRIPTION_NAME, 'price_monthly_test');

    ConfigureSubscriptionCheckout::apply($subscription, $offer ?? $account->currentOffer(), $account);

    return $subscription;
}

test('a first checkout gets the catalog trial and never a promotion code field', function () {
    Carbon::setTestNow('2026-08-07 12:00:00');

    $subscription = configureCheckout($this->account);

    expect($subscription->couponId)->toBeNull()
        ->and($subscription->allowPromotionCodes)->toBeFalse()
        ->and(trialExpiresAt($subscription)?->toDateTimeString())->toBe('2026-08-14 12:00:00');
});

test('the catalog trial changes the trial a checkout gets', function () {
    Carbon::setTestNow('2026-08-07 12:00:00');
    setCatalogTrial(14);

    expect(trialExpiresAt(configureCheckout($this->account))?->toDateTimeString())->toBe('2026-08-21 12:00:00');
});

test('no offer and no trial history still gets the catalog trial', function () {
    expect(ConfigureSubscriptionCheckout::trialDays(null, $this->account))->toBe(7);
});

test('an account that had a generic trial gets none', function () {
    $this->account->update(['trial_ends_at' => now()->subDay()]);

    expect(ConfigureSubscriptionCheckout::trialDays(null, $this->account->fresh()))->toBe(0)
        ->and(trialExpiresAt(configureCheckout($this->account->fresh())))->toBeNull();
});

test('an account with a prior billable subscription gets none', function () {
    givePriorSubscription($this->account);

    expect(ConfigureSubscriptionCheckout::trialDays(null, $this->account))->toBe(0)
        ->and(trialExpiresAt(configureCheckout($this->account)))->toBeNull();
});

test('a checkout that never became billable still qualifies for the trial', function (string $status) {
    givePriorSubscription($this->account, $status);

    expect(trialExpiresAt(configureCheckout($this->account)))->not->toBeNull();
})->with(['incomplete', 'incomplete_expired']);

test('a longer offer trial wins over the catalog trial', function () {
    Carbon::setTestNow('2026-08-07 12:00:00');
    claimOfferFor($this->account, Offer::factory()->trial(14)->create());

    expect(trialExpiresAt(configureCheckout($this->account->fresh()))?->toDateTimeString())->toBe('2026-08-21 12:00:00');
});

test('the catalog trial wins when the offer trial is shorter', function () {
    claimOfferFor($this->account, Offer::factory()->trial(3)->create());

    expect(ConfigureSubscriptionCheckout::trialDays($this->account->fresh()->currentOffer(), $this->account->fresh()))->toBe(7);
});

test('clamps a one-day trial to the Stripe Checkout minimum of two days', function () {
    Carbon::setTestNow('2026-08-07 12:00:00');
    setCatalogTrial(0);
    claimOfferFor($this->account, Offer::factory()->trial(1)->create());

    expect(trialExpiresAt(configureCheckout($this->account->fresh()))?->toDateTimeString())->toBe('2026-08-09 12:00:00');
});

test('a coupon offer applies its coupon once and keeps promotion codes off', function () {
    claimOfferFor($this->account, Offer::factory()->create(['stripe_coupon_id' => 'BF26_50']));

    $subscription = configureCheckout($this->account->fresh());

    expect($subscription->couponId)->toBe('BF26_50')
        ->and($subscription->allowPromotionCodes)->toBeFalse()
        ->and(trialExpiresAt($subscription))->not->toBeNull();
});

test('an offer never turns the promotion code field on', function () {
    claimOfferFor($this->account, Offer::factory()->trial(8)->create());

    expect(configureCheckout($this->account->fresh())->allowPromotionCodes)->toBeFalse();
});

test('session options carry the locale, the billing address, Terms consent and no promotion codes', function () {
    expect(ConfigureSubscriptionCheckout::sessionOptions(Locale::PortugueseBrazil))
        ->toBe([
            'locale' => 'pt-BR',
            'billing_address_collection' => 'required',
            'consent_collection' => ['terms_of_service' => 'required'],
            'allow_promotion_codes' => false,
        ]);
});

test('session options never ask Stripe to pick the language', function () {
    expect(ConfigureSubscriptionCheckout::sessionOptions(Locale::Arabic)['locale'])->toBe('en');
});
