<?php

declare(strict_types=1);

use App\Enums\Billing\BusinessStatus;
use App\Enums\Billing\CancellationType;
use App\Enums\Billing\Interval;
use App\Enums\Billing\OfferReach;
use App\Models\Account;
use App\Models\BusinessSnapshot;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\SupportChatDailyCounter;
use App\Models\User;

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
});

function endpointPlan(string $slug, int $monthly = 1190, int $yearly = 11900): Plan
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

function endpointAccount(Plan $plan, array $attributes = []): Account
{
    return Account::factory()->create(['plan_id' => $plan->id, ...$attributes]);
}

function endpointSubscription(Account $account, Plan $plan, Interval $interval = Interval::Monthly, array $attributes = []): void
{
    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->unique()->bothify('????????'),
        'stripe_status' => 'active',
        'stripe_price' => $interval === Interval::Yearly
            ? "price_{$plan->slug}_yearly"
            : "price_{$plan->slug}_monthly",
        ...$attributes,
    ]);
}

function endpointSnapshot(
    Account $account,
    string $date,
    int $mrr,
    BusinessStatus $status,
    ?CancellationType $type = null,
    ?string $reason = null,
): BusinessSnapshot {
    return BusinessSnapshot::query()->create([
        'date' => $date,
        'account_id' => $account->id,
        'mrr_cents' => $mrr,
        'status' => $status,
        'cancellation_type' => $type,
        'cancellation_reason' => $reason,
    ]);
}

function endpointPayload(): array
{
    return test()->withHeaders(test()->adminHeaders)
        ->getJson(route('internal.business'))
        ->assertOk()
        ->json();
}

test('the endpoint requires the admin token and its actor header', function () {
    $this->getJson(route('internal.business'))->assertNotFound();

    $this->withHeaders(['Authorization' => 'Bearer test-site-token'])
        ->getJson(route('internal.business'))
        ->assertNotFound();

    $this->withHeaders(['Authorization' => 'Bearer test-admin-token'])
        ->getJson(route('internal.business'))
        ->assertUnprocessable();
});

test('the payload carries the current figures, the three windows and every section', function () {
    $payload = endpointPayload();

    expect(array_keys($payload))->toBe([
        'generated_at',
        'today',
        'current',
        'waterfall',
        'churn',
        'trial_conversion',
        'offers',
        'support_chat',
    ])
        ->and(array_keys($payload['waterfall']))->toBe([30, 90, 365])
        ->and(array_keys($payload['churn']))->toBe(['monthly', 30, 90, 365])
        ->and(array_keys($payload['trial_conversion']))->toBe([30, 90, 365])
        ->and($payload['current']['mrr_cents'])->toBe(0)
        ->and($payload['current']['paying_accounts'])->toBe(0);
});

test('the current MRR and paying Accounts are computed live by the same rules', function () {
    $plan = endpointPlan('endpoint-current');

    $monthly = endpointAccount($plan);
    endpointSubscription($monthly, $plan);
    $monthly->forceFill(['first_paid_at' => now()->subMonth()])->save();

    $yearly = endpointAccount($plan);
    endpointSubscription($yearly, $plan, Interval::Yearly);
    $yearly->forceFill(['first_paid_at' => now()->subMonth()])->save();

    $trial = endpointAccount($plan);
    endpointSubscription($trial, $plan, attributes: [
        'stripe_status' => 'trialing',
        'trial_started_at' => now()->subDay(),
        'trial_ends_at' => now()->addDays(6),
    ]);

    $suspended = endpointAccount($plan);
    endpointSubscription($suspended, $plan);
    $suspended->forceFill(['first_paid_at' => now()->subMonth(), 'suspended_at' => now()])->save();

    $complimentary = endpointAccount($plan, ['complimentary_since' => now()->subDay()]);

    $current = endpointPayload()['current'];

    expect($current['mrr_cents'])->toBe(1190 + 992)
        ->and($current['paying_accounts'])->toBe(2)
        ->and($current['accounts'])->toBe(5)
        ->and($current['status_counts']['paying'])->toBe(2)
        ->and($current['status_counts']['trialing'])->toBe(1)
        ->and($current['status_counts']['suspended'])->toBe(1)
        ->and($current['status_counts']['complimentary'])->toBe(1)
        ->and($current['plan_mix'][0]['plan_name'])->toBe('Endpoint-current')
        ->and($current['plan_mix'][0]['accounts'])->toBe(5);
});

test('the waterfall classifies each Account against its baseline', function () {
    $plan = endpointPlan('endpoint-waterfall');
    $baseline = now()->subDays(31)->toDateString();
    $today = now('UTC')->toDateString();

    $new = endpointAccount($plan);
    endpointSnapshot($new, $today, 2000, BusinessStatus::Paying);

    $expansion = endpointAccount($plan);
    endpointSnapshot($expansion, $baseline, 1000, BusinessStatus::Paying);
    endpointSnapshot($expansion, $today, 1500, BusinessStatus::Paying);

    $contraction = endpointAccount($plan);
    endpointSnapshot($contraction, $baseline, 1500, BusinessStatus::Paying);
    endpointSnapshot($contraction, $today, 800, BusinessStatus::Paying);

    $churn = endpointAccount($plan);
    endpointSnapshot($churn, $baseline, 1000, BusinessStatus::Paying);
    endpointSnapshot($churn, $today, 0, BusinessStatus::Lapsed);

    $steady = endpointAccount($plan);
    endpointSnapshot($steady, $baseline, 1000, BusinessStatus::Paying);
    endpointSnapshot($steady, $today, 1000, BusinessStatus::Paying);

    $waterfall = endpointPayload()['waterfall']['30'];

    expect($waterfall['new'])->toBe(['accounts' => 1, 'mrr_cents' => 2000])
        ->and($waterfall['expansion'])->toBe(['accounts' => 1, 'mrr_cents' => 500])
        ->and($waterfall['contraction'])->toBe(['accounts' => 1, 'mrr_cents' => -700])
        ->and($waterfall['churn'])->toBe(['accounts' => 1, 'mrr_cents' => -1000]);
});

test('churn excludes trials, complimentary plans and suspension, and counts type and reason', function () {
    $plan = endpointPlan('endpoint-churn');
    $baseline = now()->subDays(31)->toDateString();
    $today = now('UTC')->toDateString();

    $voluntary = endpointAccount($plan);
    endpointSnapshot($voluntary, $baseline, 1000, BusinessStatus::Paying);
    endpointSnapshot($voluntary, $today, 0, BusinessStatus::Lapsed, CancellationType::Voluntary, 'too_expensive');

    $failed = endpointAccount($plan);
    endpointSnapshot($failed, $baseline, 1000, BusinessStatus::Paying);
    endpointSnapshot($failed, $today, 0, BusinessStatus::Lapsed, CancellationType::PaymentFailed, 'not_useful');

    $admin = endpointAccount($plan);
    endpointSnapshot($admin, $baseline, 1000, BusinessStatus::Paying);
    endpointSnapshot($admin, $today, 0, BusinessStatus::Lapsed, CancellationType::Admin);

    $suspended = endpointAccount($plan);
    endpointSnapshot($suspended, $baseline, 1000, BusinessStatus::Paying);
    endpointSnapshot($suspended, $today, 0, BusinessStatus::Suspended);

    $trialEnded = endpointAccount($plan);
    endpointSnapshot($trialEnded, $baseline, 0, BusinessStatus::Trialing);
    endpointSnapshot($trialEnded, $today, 0, BusinessStatus::Lapsed);

    $complimentaryEnded = endpointAccount($plan);
    endpointSnapshot($complimentaryEnded, $baseline, 0, BusinessStatus::Complimentary);
    endpointSnapshot($complimentaryEnded, $today, 0, BusinessStatus::Lapsed);

    $churn = endpointPayload()['churn']['30'];

    expect($churn['accounts'])->toBe(3)
        ->and($churn['mrr_cents'])->toBe(3000)
        ->and($churn['paying_at_start'])->toBe(4)
        ->and($churn['rate'])->toBe(0.75)
        ->and($churn['types'])->toEqual(['voluntary' => 1, 'payment_failed' => 1, 'admin' => 1])
        ->and($churn['reasons'])->toEqual(['too_expensive' => 1, 'not_useful' => 1]);
});

test('the monthly churn rate is the month\'s churned Accounts over the paying Accounts at its start', function () {
    $plan = endpointPlan('endpoint-monthly-rate');

    // Freeze mid-month so the month's start and today are distinct days,
    // whatever day the suite runs on.
    $this->travelTo(now('UTC')->startOfMonth()->addDays(14)->setTime(12, 0));

    $monthStart = now('UTC')->startOfMonth()->toDateString();
    $today = now('UTC')->toDateString();

    $paying = collect(range(1, 8))->map(function () use ($plan, $monthStart): Account {
        $account = endpointAccount($plan);
        endpointSnapshot($account, $monthStart, 1000, BusinessStatus::Paying);

        return $account;
    });

    foreach ($paying->take(2) as $account) {
        endpointSnapshot($account, $today, 0, BusinessStatus::Lapsed, CancellationType::Voluntary);
    }

    $churn = endpointPayload()['churn'];

    expect($churn['monthly'])
        ->toMatchArray([
            'start' => $monthStart,
            'end' => $today,
            'accounts' => 2,
            'mrr_cents' => 2000,
            'paying_at_start' => 8,
            'rate' => 0.25,
        ])
        ->and($churn['90']['rate'])->toBeNull()
        ->and($churn['365']['rate'])->toBeNull();
});

test('trial conversion counts converted, pending retries and repeat cards apart', function () {
    $plan = endpointPlan('endpoint-conversion');

    $converted = endpointAccount($plan, ['first_invoice_id' => 'in_converted']);
    endpointSubscription($converted, $plan, attributes: [
        'trial_started_at' => now()->subDays(20),
        'trial_ended_at' => now()->subDays(10),
    ]);

    $pending = endpointAccount($plan);
    endpointSubscription($pending, $plan, attributes: [
        'stripe_status' => 'past_due',
        'trial_started_at' => now()->subDays(20),
        'trial_ended_at' => now()->subDays(10),
    ]);

    $notConverted = endpointAccount($plan);
    endpointSubscription($notConverted, $plan, attributes: [
        'stripe_status' => 'canceled',
        'trial_started_at' => now()->subDays(20),
        'trial_ended_at' => now()->subDays(5),
    ]);

    $repeatCard = endpointAccount($plan, ['first_invoice_id' => 'in_repeat']);
    endpointSubscription($repeatCard, $plan, attributes: [
        'trial_started_at' => now()->subDays(6),
        'trial_ended_at' => now()->subDays(5),
        'trial_skipped_at' => now()->subDays(5),
        'paid_without_trial_at' => now()->subDays(4),
    ]);

    $conversion = endpointPayload()['trial_conversion']['30'];

    expect($conversion['ended'])->toBe(3)
        ->and($conversion['converted'])->toBe(1)
        ->and($conversion['pending'])->toBe(1)
        ->and($conversion['not_converted'])->toBe(1)
        ->and($conversion['paid_without_trial'])->toBe(1)
        ->and($conversion['rate'])->toBe(0.5);
});

test('Offers report redemptions, the general share and the MRR still on them', function () {
    $plan = endpointPlan('endpoint-offers');

    $general = Offer::factory()->general()->percent(20)->forever()
        ->covering([$plan])
        ->limitedTo(10)
        ->create();
    $link = Offer::factory()->link()->percent(10)->once()
        ->covering([$plan])
        ->create();

    $checkouts = Account::factory()->count(4)->create(['checkout_completed_at' => now()]);

    foreach ($checkouts->take(2) as $account) {
        $account->offers()->attach($general->id, ['claimed_at' => now(), 'redeemed_at' => now()]);
    }

    $paying = endpointAccount($plan);
    endpointSubscription($paying, $plan, attributes: ['offer_id' => $general->id]);
    $paying->forceFill(['first_paid_at' => now()->subMonth()])->save();

    $offers = collect(endpointPayload()['offers'])->keyBy('id');

    expect($offers[$general->id])->toMatchArray([
        'reach' => OfferReach::General->value,
        'redemptions' => 2,
        'max_redemptions' => 10,
        'redemptions_in_window' => 2,
        'checkouts_in_window' => 4,
        'share' => 0.5,
        'mrr_cents' => 952,
    ])->and($offers[$link->id]['share'])->toBeNull()
        ->and($offers[$link->id]['checkouts_in_window'])->toBeNull();
});

test('Support chat counters are returned as daily rows and totals', function () {
    SupportChatDailyCounter::query()->create([
        'date' => now('UTC')->toDateString(),
        'conversations_started' => 2,
        'user_messages' => 5,
        'handoffs' => 1,
        'feedback_up' => 3,
        'feedback_down' => 1,
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
        'total_tokens' => 150,
        'list_cost_micros' => 999,
    ]);

    SupportChatDailyCounter::query()->create([
        'date' => now('UTC')->subDay()->toDateString(),
        'conversations_started' => 1,
        'user_messages' => 2,
    ]);

    $support = endpointPayload()['support_chat'];

    expect($support['totals'])->toBe([
        'conversations_started' => 3,
        'user_messages' => 7,
        'handoffs' => 1,
        'feedback_up' => 3,
        'feedback_down' => 1,
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
        'total_tokens' => 150,
        'list_cost_micros' => 999,
    ])->and($support['days'])->toHaveCount(2);
});

test('the payload carries no customer email, name, post or transcript', function () {
    $plan = endpointPlan('endpoint-content');

    $account = endpointAccount($plan);
    $owner = User::factory()->create([
        'account_id' => $account->id,
        'email' => 'private-customer@example.com',
        'name' => 'Private Customer Name',
    ]);
    $account->update(['owner_id' => $owner->id]);

    endpointSubscription($account, $plan);
    $account->forceFill(['first_paid_at' => now()->subMonth()])->save();

    $response = $this->withHeaders($this->adminHeaders)->getJson(route('internal.business'));

    $response->assertOk();

    $body = $response->getContent();

    expect($body)->not->toContain('private-customer@example.com')
        ->and($body)->not->toContain('Private Customer Name')
        ->and($body)->not->toContain($account->id)
        ->and($response->json('current.mrr_cents'))->toBe(1190);
});
