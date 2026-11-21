<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Models\Account;
use App\Models\Media;
use App\Models\Plan;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

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

test('customers paginate with the configured page size', function () {
    User::factory()->count(30)->create();

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index'));

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonPath('meta.total', 30)
        ->assertJsonCount(25, 'data');
});

test('search matches an owner email', function () {
    User::factory()->create(['email' => 'ann@example.com']);
    User::factory()->create(['email' => 'bob@example.com']);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index', ['search' => 'ann@']));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.owner.email', 'ann@example.com');
});

test('search matches a member email in the same account', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    User::factory()->create([
        'email' => 'member@example.com',
        'account_id' => $owner->account_id,
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index', ['search' => 'member@']));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.owner.email', 'owner@example.com');
});

test('an account without an owner is not a customer', function () {
    User::factory()->create();
    Account::factory()->create(['owner_id' => null]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index'));

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('the list carries the subscription status', function () {
    $owner = User::factory()->create();
    $owner->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
        'ends_at' => now()->addDay(),
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index'));

    $response->assertOk()
        ->assertJsonPath('data.0.subscription_status', 'canceled');
});

test('customer detail returns the owner, plan, Subscription, usage, members and recent posts', function () {
    $owner = User::factory()->create(['email' => 'ann@example.com']);
    $account = $owner->account;

    $plan = Plan::where('slug', 'standard')->firstOrFail();
    $monthly = $plan->priceFor(Interval::Monthly);
    $monthly->update(['stripe_price_id' => 'price_standard_monthly']);

    $account->forceFill([
        'plan_id' => $plan->id,
        'quota_bytes' => 1048576,
    ])->save();

    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_standard_monthly',
        'trial_ends_at' => now()->addDays(3),
    ]);

    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    Media::factory()->temporaryUpload($workspace)->create(['size' => 2048]);

    User::factory()->count(2)->create(['account_id' => $account->id]);

    $posts = collect();

    for ($i = 0; $i < 12; $i++) {
        $posts->push(Post::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'content' => 'private post content',
            'created_at' => now()->subMinutes(12 - $i),
        ]));
    }

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $account->id));

    $response->assertOk()
        ->assertJsonPath('owner.email', 'ann@example.com')
        ->assertJsonPath('plan.slug', 'standard')
        ->assertJsonPath('plan.quota_bytes', 5368709120)
        ->assertJsonPath('plan.ai_credits_per_month', 6250)
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonPath('subscription.interval', 'monthly')
        ->assertJsonPath('subscription.amount', 1790)
        ->assertJsonPath('storage.used_bytes', 2048)
        ->assertJsonPath('storage.quota_bytes', 1048576)
        ->assertJsonPath('members_count', 3);

    expect($response->json('access_state'))->toBeNull()
        ->and($response->json('lifecycle_emails'))->toBeNull();

    $recent = $response->json('recent_posts');

    expect($recent)->toHaveCount(10)
        ->and($recent[0]['id'])->toBe($posts->last()->id)
        ->and($recent[0])->not->toHaveKey('content')
        ->and($recent[0])->not->toHaveKey('error_context');

    $returned = collect($recent)->pluck('id');

    expect($returned)->not->toContain($posts->first()->id)
        ->and($returned)->toContain($posts->last()->id);
});

test('an unknown customer is 404', function () {
    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', Str::uuid()))
        ->assertNotFound();
});
