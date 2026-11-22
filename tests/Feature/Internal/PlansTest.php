<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Models\Workspace;

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
    $this->siteHeaders = ['Authorization' => 'Bearer test-site-token'];
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function catalogPlanPayload(array $overrides = []): array
{
    return [
        'slug' => 'starter',
        'name' => 'Starter',
        'quota_bytes' => 1024 ** 3,
        'ai_credits_per_month' => 1200,
        'monthly_amount' => 990,
        'sort' => 5,
        ...$overrides,
    ];
}

function catalogOwner(Plan $plan, string $name, string $email, int $usedBytes, ?int $quotaBytes = null): Account
{
    $owner = User::factory()->create(['name' => $name, 'email' => $email]);
    $account = $owner->account;
    $account->update(['plan_id' => $plan->id]);
    $account->forceFill(['quota_bytes' => $quotaBytes])->save();

    $workspace = Workspace::factory()->create(['account_id' => $account->id, 'user_id' => $owner->id]);
    Media::factory()->temporaryUpload($workspace)->create(['size' => $usedBytes]);

    return $account;
}

test('creating a Plan creates one Product and two Prices in Stripe and stores their ids', function () {
    $stripe = fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.plans.store'), catalogPlanPayload())
        ->assertCreated()
        ->assertJsonPath('slug', 'starter')
        ->assertJsonPath('stripe_product_id', $stripe->productIds()[0])
        ->assertJsonPath('prices.monthly.amount', 990)
        ->assertJsonPath('prices.yearly.amount', 9900)
        ->assertJsonPath('prices.monthly.is_active', true);

    $plan = Plan::where('slug', 'starter')->firstOrFail();
    $monthly = $plan->priceFor(Interval::Monthly);
    $yearly = $plan->priceFor(Interval::Yearly);

    expect($plan->stripe_product_id)->toBe($stripe->productIds()[0])
        ->and($monthly?->stripe_price_id)->toBe($stripe->priceIds()[0])
        ->and($yearly?->stripe_price_id)->toBe($stripe->priceIds()[1])
        ->and($monthly?->is_active)->toBeTrue()
        ->and($yearly?->is_active)->toBeTrue();

    $products = $stripe->requestsFor('post', '/v1/products');
    $prices = $stripe->requestsFor('post', '/v1/prices');

    expect($products)->toHaveCount(1)
        ->and($products[0]['params']['name'])->toBe('Starter')
        ->and($products[0]['params']['metadata']['rxdesk_plan_slug'])->toBe('starter')
        ->and($prices)->toHaveCount(2)
        ->and($prices[0]['params']['unit_amount'])->toBe(990)
        ->and($prices[0]['params']['currency'])->toBe('usd')
        ->and($prices[0]['params']['recurring']['interval'])->toBe('month')
        ->and($prices[0]['params']['metadata']['rxdesk_interval'])->toBe('monthly')
        ->and($prices[1]['params']['unit_amount'])->toBe(9900)
        ->and($prices[1]['params']['recurring']['interval'])->toBe('year')
        ->and($prices[1]['params']['metadata']['rxdesk_interval'])->toBe('yearly');

    $entry = AuditLog::where('action', 'plan.create')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('plans')
        ->and($entry->target_id)->toBe($plan->id)
        ->and($entry->after['stripe_product_id'])->toBe($stripe->productIds()[0])
        ->and($entry->after['prices']['monthly']['stripe_price_id'])->toBe($stripe->priceIds()[0])
        ->and($entry->after['prices']['yearly']['stripe_price_id'])->toBe($stripe->priceIds()[1]);
});

test('a Plan write rejects a bad slug, missing fields and a zero amount without reaching Stripe', function (array $payload, array $errors) {
    $stripe = fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.plans.store'), catalogPlanPayload($payload))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);

    expect($stripe->requests)->toBe([])
        ->and(Plan::where('slug', 'starter')->exists())->toBeFalse();
})->with([
    'uppercase slug' => [['slug' => 'Starter'], ['slug']],
    'duplicate slug' => [['slug' => 'mini'], ['slug']],
    'empty name' => [['name' => ''], ['name']],
    'missing quota' => [['quota_bytes' => null], ['quota_bytes']],
    'missing credits' => [['ai_credits_per_month' => null], ['ai_credits_per_month']],
    'zero amount' => [['monthly_amount' => 0], ['monthly_amount']],
    'amount the column cannot hold' => [['monthly_amount' => 100000000], ['monthly_amount']],
]);

test('a Stripe error saves nothing and answers 502', function () {
    $stripe = fakeCatalogStripe();
    $stripe->failOn('post', '/v1/prices');

    $rows = PlanPrice::count();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.plans.store'), catalogPlanPayload())
        ->assertStatus(502)
        ->assertJson(['message' => 'Stripe rejected the Catalog change. Nothing was saved.']);

    expect(Plan::where('slug', 'starter')->exists())->toBeFalse()
        ->and(PlanPrice::count())->toBe($rows)
        ->and(AuditLog::count())->toBe(0);
});

test('the Plan list paginates, searches name and slug and filters archived', function () {
    Plan::factory()->archived()->create(['slug' => 'legacy', 'name' => 'Legacy', 'sort' => 9]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.plans.index'))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('data.0.slug', 'mini')
        ->assertJsonStructure(['data' => [[
            'id',
            'slug',
            'name',
            'quota_bytes',
            'ai_credits_per_month',
            'sort',
            'is_archived',
            'stripe_product_id',
            'prices' => ['monthly', 'yearly'],
            'created_at',
            'updated_at',
        ]]]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.plans.index', ['search' => 'leg']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'legacy');

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.plans.index', ['archived' => '0']))
        ->assertOk()
        ->assertJsonCount(4, 'data');

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.plans.index', ['archived' => '1']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'legacy');
});

test('renaming a Plan updates the Product and creates no Prices', function () {
    $stripe = fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.plans.store'), catalogPlanPayload())
        ->assertCreated();

    $plan = Plan::where('slug', 'starter')->firstOrFail();
    $productId = $plan->stripe_product_id;
    $rows = PlanPrice::count();
    $stripe->requests = [];

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.plans.update', $plan), ['name' => 'Starter Plus'])
        ->assertOk()
        ->assertJsonPath('name', 'Starter Plus');

    $updates = $stripe->requestsFor('post', "/v1/products/{$productId}");

    expect($stripe->requestsFor('post', '/v1/prices'))->toBe([])
        ->and($updates)->toHaveCount(1)
        ->and($updates[0]['params']['name'])->toBe('Starter Plus')
        ->and(PlanPrice::count())->toBe($rows);

    $entry = AuditLog::where('action', 'plan.update')->sole();

    expect($entry->before['name'])->toBe('Starter')
        ->and($entry->after['name'])->toBe('Starter Plus');
});

test('a price change creates new Prices, archives the old ones and keeps them resolving', function () {
    $stripe = fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.plans.store'), catalogPlanPayload())
        ->assertCreated();

    $plan = Plan::where('slug', 'starter')->firstOrFail();
    $oldMonthly = $plan->priceFor(Interval::Monthly);
    $oldYearly = $plan->priceFor(Interval::Yearly);
    $oldMonthlyId = $oldMonthly?->stripe_price_id;
    $oldYearlyId = $oldYearly?->stripe_price_id;
    $stripe->requests = [];

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.plans.update', $plan), ['monthly_amount' => 1290])
        ->assertOk()
        ->assertJsonPath('prices.monthly.amount', 1290)
        ->assertJsonPath('prices.yearly.amount', 12900);

    $plan->refresh();
    $newMonthly = $plan->priceFor(Interval::Monthly);
    $newYearly = $plan->priceFor(Interval::Yearly);
    $newIds = $stripe->priceIds();

    expect($newMonthly?->id)->not->toBe($oldMonthly?->id)
        ->and($newMonthly?->amount)->toBe(1290)
        ->and($newMonthly?->stripe_price_id)->toBe($newIds[2])
        ->and($newYearly?->stripe_price_id)->toBe($newIds[3])
        ->and($oldMonthly?->fresh()->is_active)->toBeFalse()
        ->and($oldYearly?->fresh()->is_active)->toBeFalse()
        ->and($plan->intervalFor($oldMonthlyId))->toBe(Interval::Monthly)
        ->and($plan->intervalFor($oldYearlyId))->toBe(Interval::Yearly)
        ->and(Plan::forStripePrices([$oldMonthlyId])->whereKey($plan->id)->exists())->toBeTrue();

    expect($stripe->requestsFor('post', '/v1/prices'))->toHaveCount(2)
        ->and($stripe->findPrice($oldMonthlyId)['active'])->toBeFalse()
        ->and($stripe->findPrice($oldYearlyId)['active'])->toBeFalse();

    $entry = AuditLog::where('action', 'plan.update')->sole();

    expect($entry->before['prices']['monthly']['stripe_price_id'])->toBe($oldMonthlyId)
        ->and($entry->after['prices']['monthly']['stripe_price_id'])->toBe($newIds[2])
        ->and($entry->after['prices']['monthly']['amount'])->toBe(1290);
});

test('archiving deactivates the Product and its active Prices and is a no-op the second time', function () {
    $stripe = fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.plans.store'), catalogPlanPayload())
        ->assertCreated();

    $plan = Plan::where('slug', 'starter')->firstOrFail();
    $productId = $plan->stripe_product_id;
    $oldMonthly = $plan->priceFor(Interval::Monthly);
    $oldYearly = $plan->priceFor(Interval::Yearly);
    $stripe->requests = [];

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.plans.archive', $plan))
        ->assertOk()
        ->assertJsonPath('is_archived', true)
        ->assertJsonPath('prices.monthly', null)
        ->assertJsonPath('prices.yearly', null);

    $plan->refresh();

    expect($plan->is_archived)->toBeTrue()
        ->and($plan->prices()->where('is_active', true)->count())->toBe(0)
        ->and($plan->intervalFor($oldMonthly?->stripe_price_id))->toBe(Interval::Monthly)
        ->and($stripe->findProduct($productId)['active'])->toBeFalse()
        ->and($stripe->findPrice($oldMonthly?->stripe_price_id)['active'])->toBeFalse()
        ->and($stripe->findPrice($oldYearly?->stripe_price_id)['active'])->toBeFalse();

    $requests = count($stripe->requests);

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.plans.archive', $plan))
        ->assertOk();

    expect($stripe->requests)->toHaveCount($requests)
        ->and(AuditLog::where('action', 'plan.archive')->count())->toBe(1);
});

test('an archived Plan cannot be updated', function () {
    $plan = Plan::where('slug', 'mini')->firstOrFail();
    $plan->update(['is_archived' => true]);

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.plans.update', $plan), ['name' => 'Nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan');
});

test('the Quota-cut dry run names only the Owners without an override who would go over', function () {
    $plan = Plan::factory()->create(['name' => 'Quota Plan', 'quota_bytes' => 5 * 1024 ** 3]);
    $other = Plan::factory()->create(['name' => 'Other Plan', 'quota_bytes' => 5 * 1024 ** 3]);

    $listed = catalogOwner($plan, 'Ann', 'ann@example.com', 3 * 1024 ** 3);
    catalogOwner($plan, 'Bob', 'bob@example.com', 1024 ** 3);
    catalogOwner($plan, 'Cid', 'cid@example.com', 3 * 1024 ** 3, quotaBytes: 5 * 1024 ** 3);
    catalogOwner($other, 'Dee', 'dee@example.com', 3 * 1024 ** 3);

    $orphan = Account::factory()->create(['plan_id' => $plan->id]);
    $orphanWorkspace = Workspace::factory()->create(['account_id' => $orphan->id]);
    Media::factory()->temporaryUpload($orphanWorkspace)->create(['size' => 3 * 1024 ** 3]);

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.plans.quota-cut-dry-run', $plan), ['quota_bytes' => 1024 ** 3])
        ->assertOk()
        ->assertJsonPath('quota_bytes', 1024 ** 3)
        ->assertJsonCount(1, 'owners')
        ->assertJsonPath('owners.0.account_id', $listed->id)
        ->assertJsonPath('owners.0.owner_name', 'Ann')
        ->assertJsonPath('owners.0.owner_email', 'ann@example.com')
        ->assertJsonPath('owners.0.used_bytes', 3 * 1024 ** 3);
});

test('the site token cannot reach the Catalog reads or any write', function () {
    $plan = Plan::where('slug', 'mini')->firstOrFail();

    $this->withHeaders($this->siteHeaders)
        ->getJson(route('internal.plans.index'))
        ->assertNotFound();

    $this->withHeaders($this->siteHeaders)
        ->getJson(route('internal.catalog-settings.show'))
        ->assertNotFound();

    $this->withHeaders($this->siteHeaders)
        ->postJson(route('internal.plans.store'), [])
        ->assertNotFound();

    $this->withHeaders($this->siteHeaders)
        ->patchJson(route('internal.plans.update', $plan), [])
        ->assertNotFound();

    $this->withHeaders($this->siteHeaders)
        ->postJson(route('internal.plans.archive', $plan))
        ->assertNotFound();

    $this->withHeaders($this->siteHeaders)
        ->postJson(route('internal.plans.quota-cut-dry-run', $plan), ['quota_bytes' => 1])
        ->assertNotFound();

    $this->withHeaders($this->siteHeaders)
        ->patchJson(route('internal.catalog-settings.update'), [])
        ->assertNotFound();
});
