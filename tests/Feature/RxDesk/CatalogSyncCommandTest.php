<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Support\Facades\Artisan;

test('the sync gives every seeded Plan a Product and two Prices and fills their ids in place', function () {
    $stripe = fakeCatalogStripe();

    expect(Artisan::call('rxdesk:catalog-sync'))->toBe(0)
        ->and(Artisan::output())->toContain('Synced 4 Plans.');

    $mini = Plan::where('slug', 'mini')->firstOrFail();

    expect($mini->stripe_product_id)->toBe($stripe->productIds()[0])
        ->and($mini->priceFor(Interval::Monthly)?->stripe_price_id)->toBe($stripe->priceIds()[0])
        ->and($mini->priceFor(Interval::Yearly)?->stripe_price_id)->toBe($stripe->priceIds()[1])
        ->and(PlanPrice::count())->toBe(8)
        ->and($stripe->requestsFor('post', '/v1/products'))->toHaveCount(4)
        ->and($stripe->requestsFor('post', '/v1/prices'))->toHaveCount(8)
        ->and(AuditLog::where('action', 'plan.sync')->where('actor', 'cli')->count())->toBe(4);
});

test('an empty Catalog is seeded from the committed default before it syncs', function () {
    fakeCatalogStripe();

    PlanPrice::query()->delete();
    Plan::query()->delete();

    expect(Artisan::call('rxdesk:catalog-sync'))->toBe(0)
        ->and(Artisan::output())->toContain('Seeding the default Catalog.')
        ->and(Plan::count())->toBe(4)
        ->and(Plan::whereNotNull('stripe_product_id')->count())->toBe(4)
        ->and(PlanPrice::count())->toBe(8)
        ->and(Plan::where('slug', 'mini')->firstOrFail()->priceFor(Interval::Monthly)?->stripe_price_id)->not->toBeNull();
});

test('--if-needed leaves a complete Catalog untouched and makes no Stripe call', function () {
    fakeCatalogStripe();
    Artisan::call('rxdesk:catalog-sync');

    $stripe = fakeCatalogStripe();
    $stripe->requests = [];

    expect(Artisan::call('rxdesk:catalog-sync', ['--if-needed' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('already has its Stripe Products and Prices')
        ->and($stripe->requests)->toBe([]);
});

test('--if-needed syncs a Catalog that is missing its Stripe ids', function () {
    fakeCatalogStripe();

    $mini = Plan::where('slug', 'mini')->firstOrFail();
    $mini->update(['stripe_product_id' => null]);
    $stripe = fakeCatalogStripe();
    $stripe->requests = [];

    expect(Artisan::call('rxdesk:catalog-sync', ['--if-needed' => true]))->toBe(0)
        ->and($mini->fresh()->stripe_product_id)->not->toBeNull()
        ->and($stripe->requestsFor('post', '/v1/products'))->not->toBe([]);
});

test('a Stripe key change creates new Products and Prices and keeps the old rows resolving', function () {
    fakeCatalogStripe();
    Artisan::call('rxdesk:catalog-sync');

    $mini = Plan::where('slug', 'mini')->firstOrFail();
    $oldMonthlyId = $mini->priceFor(Interval::Monthly)?->stripe_price_id;
    $oldProductId = $mini->stripe_product_id;
    $oldRowId = $mini->priceFor(Interval::Monthly)?->id;
    $rows = PlanPrice::count();

    $newKey = fakeCatalogStripe();

    expect(Artisan::call('rxdesk:catalog-sync'))->toBe(0);

    $mini->refresh();
    $newMonthly = $mini->priceFor(Interval::Monthly);

    expect($newMonthly?->stripe_price_id)->toBe($newKey->priceIds()[0])
        ->and($newMonthly?->stripe_price_id)->not->toBe($oldMonthlyId)
        ->and($newMonthly?->id)->not->toBe($oldRowId)
        ->and($mini->stripe_product_id)->toBe($newKey->productIds()[0])
        ->and($mini->stripe_product_id)->not->toBe($oldProductId)
        ->and(PlanPrice::find($oldRowId)?->is_active)->toBeFalse()
        ->and($mini->intervalFor($oldMonthlyId))->toBe(Interval::Monthly)
        ->and(Plan::forStripePrices([$oldMonthlyId])->whereKey($mini->id)->exists())->toBeTrue()
        ->and(PlanPrice::count())->toBe($rows + 8);
});

test('a second sync with the same key creates no Stripe objects and no rows', function () {
    $stripe = fakeCatalogStripe();
    Artisan::call('rxdesk:catalog-sync');

    $rows = PlanPrice::count();
    $entries = AuditLog::count();
    $stripe->requests = [];

    expect(Artisan::call('rxdesk:catalog-sync'))->toBe(0);

    expect($stripe->writes())->toBe([])
        ->and(PlanPrice::count())->toBe($rows)
        ->and(AuditLog::count())->toBe($entries);
});

test('the sync corrects a Product whose name drifted', function () {
    $stripe = fakeCatalogStripe();
    Artisan::call('rxdesk:catalog-sync');

    $mini = Plan::where('slug', 'mini')->firstOrFail();
    $mini->update(['name' => 'Mini+']);
    $productId = $mini->stripe_product_id;
    $stripe->requests = [];

    Artisan::call('rxdesk:catalog-sync');

    expect($stripe->requestsFor('post', "/v1/products/{$productId}"))->toHaveCount(1)
        ->and($stripe->findProduct($productId)['name'])->toBe('Mini+')
        ->and(PlanPrice::count())->toBe(8)
        ->and(AuditLog::where('action', 'plan.sync')->count())->toBe(5);
});

test('the sync replaces and archives a Price whose amount drifted', function () {
    $stripe = fakeCatalogStripe();
    Artisan::call('rxdesk:catalog-sync');

    $mini = Plan::where('slug', 'mini')->firstOrFail();
    $oldMonthly = $mini->priceFor(Interval::Monthly);
    $oldYearly = $mini->priceFor(Interval::Yearly);
    $oldMonthly->update(['amount' => 1290]);
    $stripe->requests = [];
    $before = count($stripe->priceIds());

    Artisan::call('rxdesk:catalog-sync');

    $mini->refresh();
    $newMonthly = $mini->priceFor(Interval::Monthly);
    $newYearly = $mini->priceFor(Interval::Yearly);

    expect($newMonthly?->amount)->toBe(1290)
        ->and($newYearly?->amount)->toBe(12900)
        ->and($newMonthly?->id)->not->toBe($oldMonthly->id)
        ->and($newYearly?->id)->not->toBe($oldYearly->id)
        ->and($oldMonthly->fresh()->is_active)->toBeFalse()
        ->and($oldYearly->fresh()->is_active)->toBeFalse()
        ->and($stripe->findPrice($oldMonthly->stripe_price_id)['active'])->toBeFalse()
        ->and($stripe->findPrice($oldYearly->stripe_price_id)['active'])->toBeFalse()
        ->and($newMonthly?->stripe_price_id)->toBe($stripe->priceIds()[$before])
        ->and($newYearly?->stripe_price_id)->toBe($stripe->priceIds()[$before + 1]);
});

test('a Stripe failure leaves the database untouched and fails the command', function () {
    $stripe = fakeCatalogStripe();
    $stripe->failOn('post', '/v1/products');

    $rows = PlanPrice::count();

    expect(Artisan::call('rxdesk:catalog-sync'))->toBe(1)
        ->and(Artisan::output())->toContain('Stripe rejected the Catalog change. Nothing was saved.')
        ->and(PlanPrice::count())->toBe($rows)
        ->and(Plan::whereNotNull('stripe_product_id')->count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});
