<?php

declare(strict_types=1);

use App\Enums\Billing\BusinessStatus;
use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Support\Business\AccountBusinessState;
use App\Support\Business\BusinessStateResolver;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
});

function businessPlan(string $slug, int $monthly = 1190, int $yearly = 11900): Plan
{
    $plan = Plan::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);

    PlanPrice::factory()->create([
        'plan_id' => $plan->id,
        'interval' => Interval::Monthly,
        'amount' => $monthly,
        'stripe_price_id' => "price_{$slug}_monthly",
    ]);

    PlanPrice::factory()->yearly()->create([
        'plan_id' => $plan->id,
        'stripe_price_id' => "price_{$slug}_yearly",
        'amount' => $yearly,
    ]);

    return $plan->fresh();
}

function businessAccount(Plan $plan, array $attributes = []): Account
{
    return Account::factory()->create(['plan_id' => $plan->id, ...$attributes]);
}

function businessSubscription(Account $account, Plan $plan, Interval $interval = Interval::Monthly, array $attributes = []): Subscription
{
    return $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->unique()->bothify('????????'),
        'stripe_status' => 'active',
        'stripe_price' => $interval === Interval::Yearly
            ? "price_{$plan->slug}_yearly"
            : "price_{$plan->slug}_monthly",
        ...$attributes,
    ]);
}

function businessState(Account $account): AccountBusinessState
{
    return app(BusinessStateResolver::class)->stateFor($account, now('UTC'));
}

test('a monthly subscription is its next renewal price', function () {
    $plan = businessPlan('metrics-monthly');
    $account = businessAccount($plan);
    businessSubscription($account, $plan);

    $state = businessState($account->refresh());

    expect($state->status)->toBe(BusinessStatus::Paying)
        ->and($state->mrrCents)->toBe(1190)
        ->and($state->interval)->toBe(Interval::Monthly);
});

test('a yearly price is divided by twelve and rounded', function () {
    $plan = businessPlan('metrics-yearly', yearly: 11900);
    $account = businessAccount($plan);
    businessSubscription($account, $plan, Interval::Yearly);

    $state = businessState($account->refresh());

    expect($state->status)->toBe(BusinessStatus::Paying)
        ->and($state->mrrCents)->toBe(992)
        ->and($state->interval)->toBe(Interval::Yearly);
});

test('a trial carries no MRR', function () {
    $plan = businessPlan('metrics-trial');
    $account = businessAccount($plan);
    businessSubscription($account, $plan, attributes: [
        'stripe_status' => 'trialing',
        'trial_started_at' => now()->subDay(),
        'trial_ends_at' => now()->addDays(6),
    ]);

    $state = businessState($account->refresh());

    expect($state->status)->toBe(BusinessStatus::Trialing)
        ->and($state->mrrCents)->toBe(0);
});

test('a complimentary plan carries no MRR', function () {
    $plan = businessPlan('metrics-complimentary');
    $account = businessAccount($plan, [
        'complimentary_since' => now()->subDay(),
        'complimentary_until' => now()->addDays(30),
    ]);

    $state = businessState($account);

    expect($state->status)->toBe(BusinessStatus::Complimentary)
        ->and($state->mrrCents)->toBe(0)
        ->and($state->planId)->toBe($plan->id);
});

test('a suspended account carries no MRR even when it pays', function () {
    $plan = businessPlan('metrics-suspended');
    $account = businessAccount($plan);
    businessSubscription($account, $plan);
    $account->forceFill(['first_paid_at' => now()->subMonth(), 'suspended_at' => now()])->save();

    $state = businessState($account->refresh());

    expect($state->status)->toBe(BusinessStatus::Suspended)
        ->and($state->mrrCents)->toBe(0);
});

test('a pending first charge carries no MRR', function () {
    $plan = businessPlan('metrics-pending');
    $account = businessAccount($plan);
    businessSubscription($account, $plan, attributes: [
        'stripe_status' => 'past_due',
        'trial_started_at' => now()->subDays(10),
        'trial_ended_at' => now()->subDays(2),
    ]);

    $state = businessState($account->refresh());

    expect($state->status)->toBe(BusinessStatus::PendingFirstCharge)
        ->and($state->mrrCents)->toBe(0);
});

test('a past due account that paid before counts its next renewal', function () {
    $plan = businessPlan('metrics-past-due');
    $account = businessAccount($plan);
    $account->forceFill(['first_paid_at' => now()->subMonths(2)])->save();
    businessSubscription($account, $plan, attributes: ['stripe_status' => 'past_due']);

    $state = businessState($account->refresh());

    expect($state->status)->toBe(BusinessStatus::PastDue)
        ->and($state->mrrCents)->toBe(1190);
});

test('a subscription cancelling at the period end still counts', function () {
    $plan = businessPlan('metrics-cancelling');
    $account = businessAccount($plan);
    businessSubscription($account, $plan, attributes: ['ends_at' => now()->addDays(15)]);

    $state = businessState($account->refresh());

    expect($state->status)->toBe(BusinessStatus::Paying)
        ->and($state->mrrCents)->toBe(1190);
});

test('a lapsed account carries no MRR', function () {
    $plan = businessPlan('metrics-lapsed');
    $account = businessAccount($plan);
    $account->forceFill(['first_paid_at' => now()->subMonths(3)])->save();
    businessSubscription($account, $plan, attributes: [
        'stripe_status' => 'canceled',
        'ends_at' => now()->subDay(),
    ]);

    $state = businessState($account->refresh());

    expect($state->status)->toBe(BusinessStatus::Lapsed)
        ->and($state->mrrCents)->toBe(0);
});

test('a canceled status without an end date is lapsed too', function () {
    $plan = businessPlan('metrics-canceled');
    $account = businessAccount($plan);
    $account->forceFill(['first_paid_at' => now()->subMonths(3)])->save();
    businessSubscription($account, $plan, attributes: ['stripe_status' => 'canceled']);

    $state = businessState($account->refresh());

    expect($state->status)->toBe(BusinessStatus::Lapsed)
        ->and($state->mrrCents)->toBe(0);
});

test('a forever Offer discounts every renewal', function () {
    $plan = businessPlan('metrics-forever');
    $offer = Offer::factory()->percent(20)->forever()->covering([$plan])->create();
    $account = businessAccount($plan);
    $account->forceFill(['first_paid_at' => now()->subMonths(2)])->save();
    businessSubscription($account, $plan, attributes: ['offer_id' => $offer->id]);

    $state = businessState($account->refresh());

    expect($state->mrrCents)->toBe(952)
        ->and($state->offerId)->toBe($offer->id);
});

test('a one hundred percent Offer counts as zero MRR', function () {
    $plan = businessPlan('metrics-free-offer');
    $offer = Offer::factory()->percent(100)->forever()->covering([$plan])->create();
    $account = businessAccount($plan);
    $account->forceFill(['first_paid_at' => now()->subMonths(2)])->save();
    businessSubscription($account, $plan, attributes: ['offer_id' => $offer->id]);

    $state = businessState($account->refresh());

    expect($state->mrrCents)->toBe(0)
        ->and($state->status)->toBe(BusinessStatus::Paying);
});

test('a once Offer is in force for the first charge only', function () {
    $plan = businessPlan('metrics-once');

    $offer = Offer::factory()->percent(20)->once()->covering([$plan])->create();

    $trial = businessAccount($plan);
    businessSubscription($trial, $plan, attributes: [
        'stripe_status' => 'trialing',
        'trial_started_at' => now()->subDay(),
        'trial_ends_at' => now()->addDays(6),
        'offer_id' => $offer->id,
    ]);

    expect(businessState($trial->refresh())->offerId)->toBe($offer->id);

    $paid = businessAccount($plan);
    $paid->forceFill(['first_paid_at' => now()->subDays(35)])->save();
    businessSubscription($paid, $plan, attributes: [
        'trial_started_at' => now()->subDays(45),
        'trial_ended_at' => now()->subDays(35),
        'offer_id' => $offer->id,
    ]);

    expect(businessState($paid->refresh())->offerId)->toBeNull()
        ->and(businessState($paid->refresh())->mrrCents)->toBe(1190);
});

test('a repeating Offer applies until its months are spent', function () {
    $plan = businessPlan('metrics-repeating');
    $offer = Offer::factory()->percent(20)->repeating(3)->covering([$plan])->create();

    $within = businessAccount($plan);
    $within->forceFill(['first_paid_at' => now()->subDays(35)])->save();
    businessSubscription($within, $plan, attributes: [
        'trial_started_at' => now()->subDays(45),
        'trial_ended_at' => now()->subDays(35),
        'offer_id' => $offer->id,
    ]);

    expect(businessState($within->refresh())->mrrCents)->toBe(952);

    $spent = businessAccount($plan);
    $spent->forceFill(['first_paid_at' => now()->subDays(100)])->save();
    businessSubscription($spent, $plan, attributes: [
        'trial_started_at' => now()->subDays(110),
        'trial_ended_at' => now()->subDays(100),
        'offer_id' => $offer->id,
    ]);

    expect(businessState($spent->refresh())->offerId)->toBeNull()
        ->and(businessState($spent->refresh())->mrrCents)->toBe(1190);
});

test('an Offer that does not cover the interval is ignored', function () {
    $plan = businessPlan('metrics-coverage');
    $offer = Offer::factory()->percent(20)->forever()
        ->covering([$plan], [Interval::Monthly])
        ->create();
    $account = businessAccount($plan);
    $account->forceFill(['first_paid_at' => now()->subMonths(2)])->save();
    businessSubscription($account, $plan, Interval::Yearly, ['offer_id' => $offer->id]);

    $state = businessState($account->refresh());

    expect($state->mrrCents)->toBe(992)
        ->and($state->offerId)->toBeNull();
});

test('an ended Offer is not in force at the next renewal', function () {
    $plan = businessPlan('metrics-ended-offer');
    $offer = Offer::factory()->percent(20)->forever()->covering([$plan])->expired()->create();
    $account = businessAccount($plan);
    $account->forceFill(['first_paid_at' => now()->subMonths(2)])->save();
    businessSubscription($account, $plan, attributes: ['offer_id' => $offer->id]);

    $state = businessState($account->refresh());

    expect($state->mrrCents)->toBe(1190)
        ->and($state->offerId)->toBeNull();
});

test('the last redeemed Offer is the fallback when the subscription has no metadata', function () {
    $plan = businessPlan('metrics-fallback');
    $offer = Offer::factory()->percent(20)->forever()->covering([$plan])->create();
    $account = businessAccount($plan);
    $account->forceFill(['first_paid_at' => now()->subMonths(2)])->save();
    businessSubscription($account, $plan);
    $account->offers()->attach($offer->id, ['claimed_at' => now()->subMonths(2), 'redeemed_at' => now()->subMonths(2)]);

    $state = businessState($account->refresh());

    expect($state->offerId)->toBe($offer->id)
        ->and($state->mrrCents)->toBe(952);
});

test('an account with only an abandoned checkout is not in the scope', function () {
    $plan = businessPlan('metrics-abandoned');
    $account = businessAccount($plan);
    businessSubscription($account, $plan, attributes: ['stripe_status' => 'incomplete']);

    expect(Account::query()->withBillingFootprint()->whereKey($account->id)->exists())->toBeFalse();
});
