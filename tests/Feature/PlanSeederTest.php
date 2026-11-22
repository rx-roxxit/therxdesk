<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Models\Plan;
use App\Models\PlanPrice;
use Database\Seeders\PlanSeeder;

test('seeder creates the four plans of the catalog', function () {
    $mini = Plan::where('slug', 'mini')->firstOrFail();
    $standard = Plan::where('slug', 'standard')->firstOrFail();
    $plus = Plan::where('slug', 'plus')->firstOrFail();
    $max = Plan::where('slug', 'max')->firstOrFail();

    expect($mini->name)->toBe('Mini')
        ->and($mini->quota_bytes)->toBe(2147483648)
        ->and($mini->ai_credits_per_month)->toBe(2500)
        ->and($standard->quota_bytes)->toBe(5368709120)
        ->and($standard->ai_credits_per_month)->toBe(10000)
        ->and($plus->quota_bytes)->toBe(21474836480)
        ->and($plus->ai_credits_per_month)->toBe(25000)
        ->and($max->quota_bytes)->toBe(53687091200)
        ->and($max->ai_credits_per_month)->toBe(40000)
        ->and(Plan::count())->toBe(4);
});

test('every seeded plan is active, unlimited in workspaces and ordered by sort', function () {
    $plans = Plan::active()->orderBy('sort')->get();

    expect($plans->pluck('slug')->all())->toBe(['mini', 'standard', 'plus', 'max'])
        ->and($plans->pluck('sort')->all())->toBe([1, 2, 3, 4])
        ->and($plans->every(fn (Plan $plan): bool => $plan->workspace_limit === null))->toBeTrue();
});

test('each plan has a monthly and a yearly price, the year costing ten months', function (string $slug, int $monthly) {
    $plan = Plan::where('slug', $slug)->firstOrFail();

    expect($plan->priceFor(Interval::Monthly)?->amount)->toBe($monthly)
        ->and($plan->priceFor(Interval::Yearly)?->amount)->toBe($monthly * 10)
        ->and($plan->prices()->count())->toBe(2);
})->with([
    ['mini', 1190],
    ['standard', 1790],
    ['plus', 2990],
    ['max', 4990],
]);

test('seeded prices have no Stripe id yet', function () {
    expect(PlanPrice::whereNotNull('stripe_price_id')->count())->toBe(0);
});

test('seeder is idempotent', function () {
    $this->seed(PlanSeeder::class);

    expect(Plan::count())->toBe(4)
        ->and(PlanPrice::count())->toBe(8);
});

test('the seeder archives a plan that is not part of the catalog and never deletes it', function () {
    $legacy = Plan::factory()->create(['slug' => 'legacy', 'name' => 'Legacy']);

    $this->seed(PlanSeeder::class);

    expect($legacy->fresh()->is_archived)->toBeTrue()
        ->and(Plan::count())->toBe(5);
});

test('a changed price adds an active row and deactivates the previous one', function () {
    $mini = Plan::where('slug', 'mini')->firstOrFail();
    $old = $mini->priceFor(Interval::Monthly);
    $old->update(['amount' => 990]);

    $this->seed(PlanSeeder::class);

    $active = $mini->priceFor(Interval::Monthly);

    expect($active->id)->not->toBe($old->id)
        ->and($active->amount)->toBe(1190)
        ->and($old->fresh()->is_active)->toBeFalse()
        ->and($mini->prices()->count())->toBe(3);
});

test('a price that already matches is left as it is', function () {
    $mini = Plan::where('slug', 'mini')->firstOrFail();
    $monthly = $mini->priceFor(Interval::Monthly);

    $this->seed(PlanSeeder::class);

    expect($mini->priceFor(Interval::Monthly)->id)->toBe($monthly->id)
        ->and($mini->prices()->count())->toBe(2);
});
