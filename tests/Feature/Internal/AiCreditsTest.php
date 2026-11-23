<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AiCreditUsage;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\Ai\AiCredits;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

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

    $this->plan = Plan::factory()->create(['ai_credits_per_month' => 1000]);
    $this->account = Account::factory()->create([
        'plan_id' => $this->plan->id,
        'trial_ends_at' => null,
    ]);
    subscribeAccount($this->account);
});

$updateAiCredits = function (array $payload): TestResponse {
    return test()->withHeaders(test()->adminHeaders)
        ->patchJson(route('internal.accounts.ai-credits.update', test()->account->id), $payload);
};

test('an override sets the monthly AI credits and is audited', function () use ($updateAiCredits) {
    $response = $updateAiCredits(['ai_credits_per_month' => 5000]);

    $response->assertOk()
        ->assertJsonPath('id', $this->account->id)
        ->assertJsonPath('ai_credits_per_month', 5000)
        ->assertJsonPath('monthly_credits', 5000)
        ->assertJsonPath('used', 0)
        ->assertJsonPath('remaining', 5000)
        ->assertJsonPath('reset_at', null);

    expect($this->account->fresh()->ai_credits_per_month)->toBe(5000);

    $entry = AuditLog::query()->where('action', 'account.ai-credits.override')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('accounts')
        ->and($entry->target_id)->toBe($this->account->id)
        ->and($entry->before['ai_credits_per_month'])->toBeNull()
        ->and($entry->after['ai_credits_per_month'])->toBe(5000);
});

test('clearing the override falls back to the plan credits and is audited', function () use ($updateAiCredits) {
    $this->account->forceFill(['ai_credits_per_month' => 5000])->save();

    $response = $updateAiCredits(['ai_credits_per_month' => null]);

    $response->assertOk()
        ->assertJsonPath('ai_credits_per_month', null)
        ->assertJsonPath('monthly_credits', 1000);

    expect($this->account->fresh()->ai_credits_per_month)->toBeNull();

    $entry = AuditLog::query()->where('action', 'account.ai-credits.override')->sole();

    expect($entry->before['ai_credits_per_month'])->toBe(5000)
        ->and($entry->after['ai_credits_per_month'])->toBeNull();
});

test('an override needs a positive amount and the field is always sent', function (array $payload) use ($updateAiCredits) {
    $updateAiCredits($payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ai_credits_per_month');

    expect($this->account->fresh()->ai_credits_per_month)->toBeNull()
        ->and(AuditLog::query()->where('action', 'account.ai-credits.override')->count())->toBe(0);
})->with([
    'zero' => [['ai_credits_per_month' => 0]],
    'missing' => [[]],
]);

test('a reset zeroes this month use and is audited', function () {
    AiCreditUsage::factory()->create([
        'account_id' => $this->account->id,
        'credits' => 300,
        'created_at' => now()->subHour(),
    ]);

    $credits = app(AiCredits::class);

    expect($credits->used($this->account))->toBe(300);

    $response = $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.ai-credits.reset', $this->account->id));

    $response->assertOk()
        ->assertJsonPath('monthly_credits', 1000)
        ->assertJsonPath('used', 0)
        ->assertJsonPath('remaining', 1000);

    expect($response->json('reset_at'))->not->toBeNull()
        ->and($this->account->fresh()->ai_credits_reset_at)->not->toBeNull()
        ->and($credits->used($this->account->fresh()))->toBe(0);

    $entry = AuditLog::query()->where('action', 'account.ai-credits.reset')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->before['used'])->toBe(300)
        ->and($entry->before['ai_credits_reset_at'])->toBeNull()
        ->and($entry->after['ai_credits_reset_at'])->not->toBeNull();
});

test('the AI credits endpoints are not reachable without the admin token', function () {
    $this->patchJson(route('internal.accounts.ai-credits.update', $this->account->id), ['ai_credits_per_month' => 5000])
        ->assertNotFound();

    $this->postJson(route('internal.accounts.ai-credits.reset', $this->account->id))
        ->assertNotFound();

    expect($this->account->fresh()->ai_credits_per_month)->toBeNull()
        ->and($this->account->fresh()->ai_credits_reset_at)->toBeNull();
});

test('an unknown account is 404', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.accounts.ai-credits.update', Str::uuid()), ['ai_credits_per_month' => 5000])
        ->assertNotFound();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.ai-credits.reset', Str::uuid()))
        ->assertNotFound();
});
