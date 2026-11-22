<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Plan;

test('AI credits fall back to the Plan', function () {
    $plan = Plan::factory()->create(['ai_credits_per_month' => 2500]);
    $account = Account::factory()->create(['plan_id' => $plan->id]);

    expect($account->aiCreditsPerMonth())->toBe(2500);
});

test('an Account override wins over the Plan', function () {
    $plan = Plan::factory()->create(['ai_credits_per_month' => 2500]);
    $account = Account::factory()->create([
        'plan_id' => $plan->id,
        'ai_credits_per_month' => 4000,
    ]);

    expect($account->aiCreditsPerMonth())->toBe(4000);
});

test('AI credits are null without an override or a Plan value', function () {
    $plan = Plan::factory()->create(['ai_credits_per_month' => null]);
    $account = Account::factory()->create(['plan_id' => $plan->id]);
    $orphan = Account::factory()->create(['plan_id' => null]);

    expect($account->aiCreditsPerMonth())->toBeNull()
        ->and($orphan->aiCreditsPerMonth())->toBeNull();
});
