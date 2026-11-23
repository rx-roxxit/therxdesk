<?php

declare(strict_types=1);

use App\Enums\Billing\BusinessStatus;
use App\Enums\Billing\CancellationType;
use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\BusinessSnapshot;
use App\Models\Plan;
use App\Models\PlanPrice;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
});

function snapshotPlan(string $slug, int $monthly = 1190): Plan
{
    $plan = Plan::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);

    PlanPrice::factory()->create([
        'plan_id' => $plan->id,
        'interval' => Interval::Monthly,
        'amount' => $monthly,
        'stripe_price_id' => "price_{$slug}_monthly",
    ]);

    return $plan->fresh();
}

function snapshotAccount(Plan $plan, array $attributes = []): Account
{
    return Account::factory()->create(['plan_id' => $plan->id, ...$attributes]);
}

function snapshotSubscription(Account $account, Plan $plan, array $attributes = []): void
{
    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->unique()->bothify('????????'),
        'stripe_status' => 'active',
        'stripe_price' => "price_{$plan->slug}_monthly",
        ...$attributes,
    ]);
}

test('the job writes each Account state and its MRR', function () {
    $plan = snapshotPlan('snapshot-states');
    $date = now('UTC')->toDateString();

    $paying = snapshotAccount($plan);
    snapshotSubscription($paying, $plan);

    $trialing = snapshotAccount($plan);
    snapshotSubscription($trialing, $plan, [
        'stripe_status' => 'trialing',
        'trial_started_at' => now()->subDay(),
        'trial_ends_at' => now()->addDays(6),
    ]);

    $pending = snapshotAccount($plan);
    snapshotSubscription($pending, $plan, [
        'stripe_status' => 'past_due',
        'trial_started_at' => now()->subDays(10),
        'trial_ended_at' => now()->subDays(2),
    ]);

    $pastDue = snapshotAccount($plan);
    $pastDue->forceFill(['first_paid_at' => now()->subMonths(2)])->save();
    snapshotSubscription($pastDue, $plan, ['stripe_status' => 'past_due']);

    $suspended = snapshotAccount($plan);
    snapshotSubscription($suspended, $plan);
    $suspended->forceFill(['suspended_at' => now()])->save();

    $complimentary = snapshotAccount($plan, ['complimentary_since' => now()->subDay()]);

    $lapsed = snapshotAccount($plan);
    $lapsed->forceFill(['first_paid_at' => now()->subMonths(3)])->save();
    snapshotSubscription($lapsed, $plan, [
        'stripe_status' => 'canceled',
        'ends_at' => now()->subDay(),
        'cancellation_type' => CancellationType::Voluntary,
        'cancellation_reason' => 'too_expensive',
    ]);

    $this->artisan('rxdesk:business-snapshot', ['--date' => $date])->assertSuccessful();

    $rows = BusinessSnapshot::query()->where('date', $date)->get()->keyBy('account_id');

    expect($rows)->toHaveCount(7)
        ->and($rows[$paying->id]->status)->toBe(BusinessStatus::Paying)
        ->and($rows[$paying->id]->mrr_cents)->toBe(1190)
        ->and($rows[$trialing->id]->status)->toBe(BusinessStatus::Trialing)
        ->and($rows[$trialing->id]->mrr_cents)->toBe(0)
        ->and($rows[$pending->id]->status)->toBe(BusinessStatus::PendingFirstCharge)
        ->and($rows[$pastDue->id]->status)->toBe(BusinessStatus::PastDue)
        ->and($rows[$pastDue->id]->mrr_cents)->toBe(1190)
        ->and($rows[$suspended->id]->status)->toBe(BusinessStatus::Suspended)
        ->and($rows[$suspended->id]->mrr_cents)->toBe(0)
        ->and($rows[$complimentary->id]->status)->toBe(BusinessStatus::Complimentary)
        ->and($rows[$lapsed->id]->status)->toBe(BusinessStatus::Lapsed)
        ->and($rows[$lapsed->id]->cancellation_type)->toBe(CancellationType::Voluntary)
        ->and($rows[$lapsed->id]->cancellation_reason)->toBe('too_expensive');
});

test('running the job twice for the same day keeps one row per Account', function () {
    $plan = snapshotPlan('snapshot-idempotent');

    $account = snapshotAccount($plan);
    snapshotSubscription($account, $plan);

    $this->artisan('rxdesk:business-snapshot')->assertSuccessful();
    $this->artisan('rxdesk:business-snapshot')->assertSuccessful();

    expect((int) BusinessSnapshot::query()->where('account_id', $account->id)->count())->toBe(1);
});

test('a snapshot outlives the Account it describes', function () {
    $plan = snapshotPlan('snapshot-survives');

    $account = snapshotAccount($plan);
    snapshotSubscription($account, $plan);
    $account->forceFill(['first_paid_at' => now()->subMonth()])->save();

    $this->artisan('rxdesk:business-snapshot')->assertSuccessful();

    $account->delete();

    expect(Account::query()->whereKey($account->id)->exists())->toBeFalse()
        ->and(BusinessSnapshot::query()->where('account_id', $account->id)->exists())->toBeTrue();
});

test('nothing is written while the app is self-hosted', function () {
    config(['trypost.self_hosted' => true]);

    $plan = snapshotPlan('snapshot-self-hosted');

    $account = snapshotAccount($plan);
    snapshotSubscription($account, $plan);

    $this->artisan('rxdesk:business-snapshot')->assertSuccessful();

    expect(BusinessSnapshot::query()->count())->toBe(0);
});
