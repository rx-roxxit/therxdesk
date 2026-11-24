<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\Offer;
use App\Models\Plan;
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

test('subscriptions paginate with the configured page size', function () {
    for ($i = 0; $i < 30; $i++) {
        User::factory()->create()->account->subscriptions()->create([
            'type' => Account::SUBSCRIPTION_NAME,
            'stripe_id' => 'sub_'.fake()->uuid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_123',
        ]);
    }

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.subscriptions.index'))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonPath('meta.total', 30)
        ->assertJsonCount(25, 'data');
});

test('the per_page query is ignored', function () {
    for ($i = 0; $i < 3; $i++) {
        User::factory()->create()->account->subscriptions()->create([
            'type' => Account::SUBSCRIPTION_NAME,
            'stripe_id' => 'sub_'.fake()->uuid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_123',
        ]);
    }

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.subscriptions.index', ['per_page' => 1]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonCount(3, 'data');
});

test('a row carries the account, owner, Plan price and Offer', function () {
    $this->travelTo(now()->startOfSecond());

    $plan = Plan::where('slug', 'standard')->firstOrFail();
    $plan->priceFor(Interval::Monthly)->update(['stripe_price_id' => 'price_standard_monthly']);

    $owner = User::factory()->create(['email' => 'subscriber@example.com']);
    $account = $owner->account;
    $account->forceFill(['plan_id' => $plan->id])->save();

    $offer = Offer::factory()->create(['slug' => 'launch', 'name' => 'Launch']);
    $account->offers()->attach($offer, ['claimed_at' => now(), 'redeemed_at' => now()]);

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_row',
        'stripe_status' => 'active',
        'stripe_price' => 'price_standard_monthly',
        'trial_ends_at' => now()->addDays(3),
    ]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.subscriptions.index'))
        ->assertOk()
        ->assertJsonPath('data.0.account.id', $account->id)
        ->assertJsonPath('data.0.account.name', $account->name)
        ->assertJsonPath('data.0.owner.email', 'subscriber@example.com')
        ->assertJsonPath('data.0.plan_name', 'Standard')
        ->assertJsonPath('data.0.interval', 'monthly')
        ->assertJsonPath('data.0.amount', 1790)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.trial_ends_at', now()->addDays(3)->toIso8601String())
        ->assertJsonPath('data.0.ends_at', null)
        ->assertJsonPath('data.0.offer.id', $offer->id)
        ->assertJsonPath('data.0.offer.slug', 'launch')
        ->assertJsonPath('data.0.offer.name', 'Launch');
});

test('a subscription with an end date reads as canceled', function () {
    $owner = User::factory()->create();
    $owner->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_ending',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
        'ends_at' => now()->subMinute(),
    ]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.subscriptions.index'))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'canceled');
});

test('only the default subscription type is listed', function () {
    $owner = User::factory()->create();
    $account = $owner->account;

    $account->subscriptions()->create([
        'type' => 'other',
        'stripe_id' => 'sub_other',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_default',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.subscriptions.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.account.id', $account->id);
});

test('the newest subscription comes first', function () {
    foreach (['Older', 'Newer'] as $index => $name) {
        $owner = User::factory()->create();
        $owner->account->update(['name' => $name]);
        $owner->account->subscriptions()->create([
            'type' => Account::SUBSCRIPTION_NAME,
            'stripe_id' => 'sub_'.fake()->uuid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_123',
            'created_at' => now()->subMinutes(2 - $index),
        ]);
    }

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.subscriptions.index'))
        ->assertOk()
        ->assertJsonPath('data.0.account.name', 'Newer')
        ->assertJsonPath('data.1.account.name', 'Older');
});

test('the subscriptions endpoint is not reachable without the admin token', function () {
    $this->getJson(route('internal.subscriptions.index'))
        ->assertNotFound();
});

test('the site token is refused by the subscriptions endpoint', function () {
    $this->withHeaders([
        'Authorization' => 'Bearer test-site-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ])->getJson(route('internal.subscriptions.index'))
        ->assertNotFound();
});
