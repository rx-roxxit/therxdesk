<?php

declare(strict_types=1);

use App\Enums\Ai\AiFeature;
use App\Models\Account;
use App\Models\AiCreditUsage;
use App\Models\Plan;
use App\Models\User;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
        'ai.default' => 'anthropic',
    ]);

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];
});

function accountOnPlan(Plan $plan, int $used = 0): Account
{
    $owner = User::factory()->create();
    $account = $owner->account->fresh();
    $account->update(['plan_id' => $plan->id]);
    $account = $account->fresh();

    if ($used > 0) {
        AiCreditUsage::factory()->create([
            'account_id' => $account->id,
            'credits' => $used,
            'created_at' => now(),
        ]);
    }

    return $account;
}

test('the balances paginate at the configured size and ignore per_page', function () {
    User::factory()->count(30)->create();

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.balances.index', ['per_page' => 100]))
        ->assertOk()
        ->assertJsonCount(25, 'data');

    expect($response->json('meta.per_page'))->toBe(25)
        ->and($response->json('meta.total'))->toBe(30);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.balances.index', ['page' => 2]))
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

test('the balances carry the Account, its Plan and its month', function () {
    $plan = Plan::factory()->create(['ai_credits_per_month' => 100]);
    $account = accountOnPlan($plan, used: 40);
    $owner = $account->owner;

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.balances.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.account_id', $account->id)
        ->assertJsonPath('data.0.account_name', $account->name)
        ->assertJsonPath('data.0.owner.email', $owner->email)
        ->assertJsonPath('data.0.plan.slug', $plan->slug)
        ->assertJsonPath('data.0.ai_credits_per_month', null)
        ->assertJsonPath('data.0.monthly_credits', 100)
        ->assertJsonPath('data.0.used', 40)
        ->assertJsonPath('data.0.remaining', 60)
        ->assertJsonStructure([
            'data' => [[
                'account_id', 'account_name',
                'owner' => ['id', 'name', 'email'],
                'plan' => ['id', 'slug', 'name'],
                'ai_credits_per_month', 'monthly_credits', 'used', 'remaining', 'renews_at', 'reset_at',
            ]],
        ]);
});

test('the zero filter keeps the exhausted Accounts and zero=0 the rest', function () {
    $plan = Plan::factory()->create(['ai_credits_per_month' => 100]);
    $empty = accountOnPlan($plan);
    $spent = accountOnPlan($plan, used: 100);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.balances.index', ['zero' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.account_id', $spent->id)
        ->assertJsonPath('data.0.remaining', 0);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.balances.index', ['zero' => 0]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.account_id', $empty->id)
        ->assertJsonPath('data.0.remaining', 100);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.balances.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('the ledger is newest first and prices the tokens at the registry rate', function () {
    $plan = Plan::factory()->create(['ai_credits_per_month' => 1000]);
    $account = accountOnPlan($plan);
    $owner = $account->owner;

    $older = AiCreditUsage::factory()->create([
        'account_id' => $account->id,
        'model' => 'claude-haiku-5-5',
        'prompt_tokens' => 200,
        'completion_tokens' => 100,
        'total_tokens' => 300,
        'credits' => 2,
        'created_at' => now()->subHour(),
    ]);

    $newer = AiCreditUsage::factory()->create([
        'account_id' => $account->id,
        'model' => 'claude-haiku-5-5',
        'prompt_tokens' => 10,
        'completion_tokens' => 10,
        'total_tokens' => 20,
        'credits' => 1,
        'created_at' => now(),
    ]);

    $data = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.ledger.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data');

    expect($data[0]['id'])->toBe($newer->id)
        ->and($data[1]['id'])->toBe($older->id)
        ->and($data[1]['list_cost_micros'])->toBe(70)
        ->and($data[1]['feature'])->toBe(AiFeature::WritingAssistant->value)
        ->and($data[1]['account']['id'])->toBe($account->id)
        ->and($data[1]['account']['owner']['email'])->toBe($owner->email)
        ->and($data[1]['prompt_tokens'])->toBe(200)
        ->and($data[1]['completion_tokens'])->toBe(100)
        ->and($data[1]['total_tokens'])->toBe(300)
        ->and($data[1]['credits'])->toBe(2);
});

test('a ledger row on a model outside the registry has no list cost', function () {
    $plan = Plan::factory()->create(['ai_credits_per_month' => 1000]);
    $account = accountOnPlan($plan);

    AiCreditUsage::factory()->create([
        'account_id' => $account->id,
        'model' => 'test-model',
        'prompt_tokens' => 200,
        'completion_tokens' => 100,
        'created_at' => now(),
    ]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.ledger.index'))
        ->assertOk()
        ->assertJsonPath('data.0.list_cost_micros', null);
});

test('the ledger paginates at the configured size', function () {
    $plan = Plan::factory()->create(['ai_credits_per_month' => 1000]);
    $account = accountOnPlan($plan);

    AiCreditUsage::factory()->count(26)->create(['account_id' => $account->id]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.ledger.index'))
        ->assertOk()
        ->assertJsonCount(25, 'data');

    expect($response->json('meta.per_page'))->toBe(25)
        ->and($response->json('meta.total'))->toBe(26);
});

test('the balance and ledger endpoints need the admin token', function () {
    $this->getJson(route('internal.ai.balances.index'))->assertNotFound();
    $this->getJson(route('internal.ai.ledger.index'))->assertNotFound();

    $this->withHeaders(['Authorization' => 'Bearer test-site-token'])
        ->getJson(route('internal.ai.balances.index'))
        ->assertNotFound();

    $this->withHeaders(['Authorization' => 'Bearer test-site-token'])
        ->getJson(route('internal.ai.ledger.index'))
        ->assertNotFound();
});
