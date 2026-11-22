<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Http\Middleware\App\CapturePlan;
use App\Models\Plan;
use App\Models\User;

test('a guest link keeps the plan and the interval', function () {
    $this->get(route('login', ['plan' => 'plus', 'interval' => Interval::Yearly->value]))
        ->assertOk()
        ->assertSessionHas(CapturePlan::PLAN_SESSION_KEY, 'plus')
        ->assertSessionHas(CapturePlan::INTERVAL_SESSION_KEY, 'yearly');
});

test('a link without a plan or interval keeps nothing', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSessionMissing(CapturePlan::PLAN_SESSION_KEY)
        ->assertSessionMissing(CapturePlan::INTERVAL_SESSION_KEY);
});

test('a junk plan or interval is dropped silently', function (array $query) {
    $this->get(route('login', $query))
        ->assertOk()
        ->assertSessionMissing(CapturePlan::PLAN_SESSION_KEY)
        ->assertSessionMissing(CapturePlan::INTERVAL_SESSION_KEY);
})->with([
    'unknown plan' => [['plan' => 'enterprise']],
    'unknown interval' => [['interval' => 'weekly']],
    'array values' => [['plan' => ['plus'], 'interval' => ['yearly']]],
    'blank values' => [['plan' => '', 'interval' => '']],
]);

test('an archived plan is dropped silently', function () {
    Plan::factory()->archived()->create(['slug' => 'legacy']);

    $this->get(route('login', ['plan' => 'legacy']))
        ->assertOk()
        ->assertSessionMissing(CapturePlan::PLAN_SESSION_KEY);
});

test('a junk link never replaces a plan and interval already kept', function () {
    $this->get(route('login', ['plan' => 'plus', 'interval' => 'yearly']))->assertOk();

    $this->get(route('login', ['plan' => 'enterprise', 'interval' => 'weekly']))
        ->assertOk()
        ->assertSessionHas(CapturePlan::PLAN_SESSION_KEY, 'plus')
        ->assertSessionHas(CapturePlan::INTERVAL_SESSION_KEY, 'yearly');
});

test('a signed-in request keeps nothing', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login', ['plan' => 'plus', 'interval' => 'yearly']))
        ->assertRedirect()
        ->assertSessionMissing(CapturePlan::PLAN_SESSION_KEY)
        ->assertSessionMissing(CapturePlan::INTERVAL_SESSION_KEY);
});
