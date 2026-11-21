<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

test('plan can be created with factory', function () {
    $plan = Plan::factory()->create([
        'slug' => 'custom',
        'name' => 'Custom',
    ]);

    expect($plan)->toBeInstanceOf(Plan::class)
        ->and($plan->slug)->toBe('custom')
        ->and($plan->name)->toBe('Custom')
        ->and($plan->is_archived)->toBeFalse();
});

test('active scope excludes archived plans', function () {
    $activeBefore = Plan::active()->count();

    $plan = Plan::where('slug', 'mini')->firstOrFail();
    $plan->update(['is_archived' => true]);

    expect(Plan::active()->count())->toBe($activeBefore - 1);
});

test('integer fields are cast correctly', function () {
    $plan = Plan::where('slug', 'standard')->firstOrFail();

    expect($plan->workspace_limit)->toBeNull()
        ->and($plan->quota_bytes)->toBeInt()
        ->and($plan->ai_credits_per_month)->toBeInt()
        ->and($plan->sort)->toBeInt();
});

test('priceFor returns the active price of the interval', function () {
    $plan = Plan::factory()->create();
    PlanPrice::factory()->create(['plan_id' => $plan->id, 'amount' => 990, 'is_active' => false, 'created_at' => now()->subDay()]);
    $current = PlanPrice::factory()->create(['plan_id' => $plan->id, 'amount' => 1190]);

    expect($plan->priceFor(Interval::Monthly)?->id)->toBe($current->id)
        ->and($plan->priceFor(Interval::Yearly))->toBeNull();
});

test('priceFor reads the loaded prices relation without another query', function () {
    $plan = Plan::with('prices')->where('slug', 'mini')->firstOrFail();

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect($plan->priceFor(Interval::Monthly)?->amount)->toBe(1190)
        ->and(DB::getQueryLog())->toBeEmpty();
});

test('intervalFor resolves an inactive price the plan no longer sells', function () {
    $plan = Plan::factory()->create();
    PlanPrice::factory()->yearly()->inactive()->create([
        'plan_id' => $plan->id,
        'stripe_price_id' => 'price_old_yearly',
    ]);

    expect($plan->intervalFor('price_old_yearly'))->toBe(Interval::Yearly)
        ->and($plan->intervalFor('price_unknown'))->toBeNull()
        ->and($plan->intervalFor(null))->toBeNull();
});

test('forStripePrices maps an inactive price back to its plan', function () {
    $plan = Plan::factory()->create();
    PlanPrice::factory()->inactive()->create([
        'plan_id' => $plan->id,
        'stripe_price_id' => 'price_old',
    ]);

    expect(Plan::forStripePrices(['price_old'])->first()?->id)->toBe($plan->id)
        ->and(Plan::forStripePrices(['price_unknown'])->first())->toBeNull();
});

test('an archived plan is left out of the shared plans while its price still resolves to it', function () {
    config(['trypost.self_hosted' => false]);

    $archived = Plan::factory()->archived()->create(['slug' => 'legacy', 'name' => 'Legacy']);
    givePlanPrice($archived, Interval::Monthly, 'price_legacy');

    $user = User::factory()->create();
    $user->account->update(['plan_id' => $archived->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    subscribeAccount($user->account);

    $this->actingAs($user->fresh())
        ->get(route('app.calendar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('plans', 4)
            ->where('plans.0.slug', 'mini')
            ->where('plans.3.slug', 'max')
        );

    expect(Plan::active()->find($archived->id))->toBeNull()
        ->and(Plan::forStripePrices(['price_legacy'])->first()?->id)->toBe($archived->id);
});

test('entryPlan is the first active plan by sort', function () {
    $entry = Plan::entryPlan();

    expect($entry?->slug)->toBe('mini');
});

test('entryPlan skips archived plans and falls back to the name order', function () {
    Plan::where('slug', 'mini')->update(['is_archived' => true]);
    Plan::factory()->create(['slug' => 'aaa', 'name' => 'AAA', 'sort' => 2]);
    Plan::factory()->create(['slug' => 'zzz', 'name' => 'ZZZ', 'sort' => 2]);

    expect(Plan::entryPlan()?->slug)->toBe('aaa');
});
