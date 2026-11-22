<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Enums\Billing\OfferDiscountType;
use App\Enums\Billing\OfferDuration;
use App\Enums\Billing\OfferReach;
use App\Models\AuditLog;
use App\Models\Offer;
use App\Models\Plan;
use App\Services\Catalog\OfferCatalog;
use Laravel\Cashier\Cashier;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];

    Offer::query()->delete();
    Plan::query()->delete();

    $this->mini = Plan::factory()->create([
        'slug' => 'mini',
        'name' => 'Mini',
        'stripe_product_id' => 'prod_mini',
        'sort' => 1,
    ]);
    givePlanPrice($this->mini, Interval::Monthly, 'price_mini_monthly', 1190);

    $this->plus = Plan::factory()->create([
        'slug' => 'plus',
        'name' => 'Plus',
        'stripe_product_id' => 'prod_plus',
        'sort' => 2,
    ]);
    givePlanPrice($this->plus, Interval::Monthly, 'price_plus_monthly', 2990);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function offerPayload(array $overrides = []): array
{
    return [
        'slug' => 'spring-sale',
        'name' => 'Spring sale',
        'reach' => OfferReach::General->value,
        'coupon' => [
            'type' => OfferDiscountType::Percent->value,
            'percent_off' => 20,
            'duration' => OfferDuration::Repeating->value,
            'duration_in_months' => 3,
        ],
        'trial_days' => 14,
        'starts_at' => now()->subDay()->toIso8601String(),
        'ends_at' => null,
        'max_redemptions' => 100,
        'show_banner' => true,
        ...$overrides,
    ];
}

test('creating a percentage offer creates its Stripe coupon with the covered products and records an audit row', function () {
    $stripe = fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.store'), offerPayload([
            'plan_ids' => [$this->mini->id],
            'intervals' => ['monthly'],
        ]))
        ->assertCreated()
        ->assertJsonPath('slug', 'spring-sale')
        ->assertJsonPath('reach', 'general')
        ->assertJsonPath('discount.type', 'percent')
        ->assertJsonPath('discount.percent_off', 20)
        ->assertJsonPath('intervals.0', 'monthly');

    $offer = Offer::query()->where('slug', 'spring-sale')->sole();

    expect($offer->reach)->toBe(OfferReach::General)
        ->and($offer->discount_type)->toBe(OfferDiscountType::Percent)
        ->and($offer->duration)->toBe(OfferDuration::Repeating)
        ->and($offer->duration_in_months)->toBe(3)
        ->and($offer->trial_days)->toBe(14)
        ->and($offer->plan_ids)->toBe([$this->mini->id])
        ->and($offer->intervals)->toBe(['monthly'])
        ->and($offer->stripe_coupon_id)->toBe(OfferCatalog::couponIdFor('spring-sale'));

    $coupons = $stripe->requestsFor('post', '/v1/coupons');

    expect($coupons)->toHaveCount(1)
        ->and($coupons[0]['params']['percent_off'])->toBe(20.0)
        ->and($coupons[0]['params']['duration'])->toBe('repeating')
        ->and($coupons[0]['params']['duration_in_months'])->toBe(3)
        ->and($coupons[0]['params']['applies_to']['products'])->toBe(['prod_mini'])
        ->and($coupons[0]['params'])->not->toHaveKey('max_redemptions');

    $entry = AuditLog::query()->where('action', 'offer.create')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('offers')
        ->and($entry->target_id)->toBe($offer->id)
        ->and($entry->after['slug'])->toBe('spring-sale');
});

test('an amount coupon is created once in USD', function () {
    $stripe = fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.store'), offerPayload([
            'coupon' => [
                'type' => OfferDiscountType::Amount->value,
                'amount_off' => 500,
                'duration' => OfferDuration::Once->value,
            ],
        ]))
        ->assertCreated()
        ->assertJsonPath('discount.amount_off', 500)
        ->assertJsonPath('discount.currency', 'usd');

    $coupon = $stripe->requestsFor('post', '/v1/coupons')[0]['params'];

    expect($coupon['amount_off'])->toBe(500)
        ->and($coupon['currency'])->toBe('usd')
        ->and($coupon['duration'])->toBe('once')
        ->and($coupon)->not->toHaveKey('percent_off');
});

test('coverage defaults to every active plan and monthly', function () {
    $stripe = fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.store'), offerPayload(['slug' => 'everything']))
        ->assertCreated();

    $offer = Offer::query()->where('slug', 'everything')->sole();

    expect($offer->plan_ids)->toBe([$this->mini->id, $this->plus->id])
        ->and($offer->intervals)->toBe(['monthly'])
        ->and($stripe->requestsFor('post', '/v1/coupons')[0]['params']['applies_to']['products'])
        ->toBe(['prod_mini', 'prod_plus']);
});

test('an offer needs a coupon, trial days, or both', function () {
    fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.store'), offerPayload([
            'coupon' => null,
            'trial_days' => 0,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('coupon');
});

test('a repeating coupon needs its months', function () {
    fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.store'), offerPayload([
            'coupon' => [
                'type' => OfferDiscountType::Percent->value,
                'percent_off' => 20,
                'duration' => OfferDuration::Repeating->value,
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('coupon.duration_in_months');
});

test('a trial-only offer creates no coupon', function () {
    $stripe = fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.store'), offerPayload([
            'slug' => 'long-trial',
            'coupon' => null,
            'trial_days' => 14,
        ]))
        ->assertCreated()
        ->assertJsonPath('discount', null);

    expect($stripe->requestsFor('post', '/v1/coupons'))->toBe([]);
});

test('a covered plan without a Stripe product is refused', function () {
    fakeCatalogStripe();
    $unsynced = Plan::factory()->create(['slug' => 'unsynced', 'stripe_product_id' => null]);

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.store'), offerPayload(['plan_ids' => [$unsynced->id]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan_ids');
});

test('a Stripe failure saves nothing and answers 502', function () {
    $stripe = fakeCatalogStripe();
    $stripe->failOn('post', '/v1/coupons', 500);

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.store'), offerPayload())
        ->assertStatus(502);

    expect(Offer::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('an existing coupon with the stable id is reused, so a retried create makes no second coupon', function () {
    $stripe = fakeCatalogStripe();
    $couponId = OfferCatalog::couponIdFor('spring-sale');

    Cashier::stripe()->coupons->create([
        'id' => $couponId,
        'duration' => 'forever',
        'percent_off' => 20,
    ]);

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.store'), offerPayload())
        ->assertCreated();

    expect($stripe->requestsFor('post', '/v1/coupons'))->toHaveCount(1)
        ->and($stripe->requestsFor('get', '/v1/coupons/'.$couponId))->toHaveCount(1)
        ->and(Offer::query()->sole()->stripe_coupon_id)->toBe($couponId);
});

test('the locked fields cannot be changed after creation', function (array $fields) {
    fakeCatalogStripe();

    $offer = Offer::factory()->create([
        'slug' => 'spring-sale',
        'plan_ids' => [$this->mini->id],
        'intervals' => ['monthly'],
    ]);

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.offers.update', $offer), $fields)
        ->assertUnprocessable();
})->with([
    'coupon' => [['coupon' => ['type' => 'percent', 'percent_off' => 50, 'duration' => 'once']]],
    'percent' => [['percent_off' => 50]],
    'amount' => [['amount_off' => 100]],
    'duration' => [['duration' => 'forever']],
    'plan_ids' => [['plan_ids' => ['00000000-0000-0000-0000-000000000000']]],
    'intervals' => [['intervals' => ['yearly']]],
    'reach' => [['reach' => 'link']],
    'slug' => [['slug' => 'another-one']],
]);

test('an update changes the editable fields and records an audit row', function () {
    fakeCatalogStripe();
    $offer = Offer::factory()->create(['slug' => 'spring-sale', 'show_banner' => false]);

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.offers.update', $offer), [
            'name' => 'Spring sale extended',
            'show_banner' => true,
            'trial_days' => 21,
            'max_redemptions' => 500,
            'ends_at' => now()->addDays(10)->toIso8601String(),
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Spring sale extended')
        ->assertJsonPath('trial_days', 21);

    $offer->refresh();

    expect($offer->name)->toBe('Spring sale extended')
        ->and($offer->show_banner)->toBeTrue()
        ->and($offer->trial_days)->toBe(21)
        ->and($offer->max_redemptions)->toBe(500);

    $entry = AuditLog::query()->where('action', 'offer.update')->sole();

    expect($entry->before['name'])->not->toBe('Spring sale extended')
        ->and($entry->after['name'])->toBe('Spring sale extended');
});

test('an ended offer is read-only and ending it again records nothing', function () {
    fakeCatalogStripe();
    $offer = Offer::factory()->create([
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subHour(),
    ]);

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.offers.update', $offer), ['name' => 'Too late'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('offer');

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.end', $offer))
        ->assertOk()
        ->assertJsonPath('is_ended', true);

    expect(AuditLog::query()->where('action', 'offer.end')->count())->toBe(0);
});

test('ending a running offer sets its end and records an audit row', function () {
    fakeCatalogStripe();
    $offer = Offer::factory()->create(['slug' => 'spring-sale']);

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.offers.end', $offer))
        ->assertOk()
        ->assertJsonPath('is_ended', true);

    expect($offer->fresh()->ends_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'offer.end')->sole()->target_id)->toBe($offer->id);
});

test('the admin index filters by reach and ended state', function () {
    fakeCatalogStripe();
    $running = Offer::factory()->general()->create(['slug' => 'running']);
    $ended = Offer::factory()->link()->create(['slug' => 'ended', 'ends_at' => now()->subDay()]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.offers.index', ['reach' => 'general', 'ended' => '0']))
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'running');

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.offers.index', ['reach' => 'link', 'ended' => '1']))
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'ended');
});
