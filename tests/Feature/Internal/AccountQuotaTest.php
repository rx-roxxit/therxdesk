<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
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
});

$updateQuota = function (string $accountId, array $payload): TestResponse {
    return test()->withHeaders(test()->adminHeaders)
        ->patchJson(route('internal.accounts.quota.update', $accountId), $payload);
};

test('setting an override writes it and records the before and after', function () use ($updateQuota) {
    $plan = Plan::where('slug', 'standard')->firstOrFail();
    $owner = User::factory()->create();
    $account = $owner->account;
    $account->forceFill(['plan_id' => $plan->id])->save();

    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    Media::factory()->temporaryUpload($workspace)->create(['size' => 1024]);

    $updateQuota($account->id, ['quota_bytes' => 1048576])
        ->assertOk()
        ->assertJsonPath('quota_bytes', 1048576)
        ->assertJsonPath('effective_quota_bytes', 1048576)
        ->assertJsonPath('used_bytes', 1024);

    expect($account->fresh()->quota_bytes)->toBe(1048576);

    $entry = AuditLog::query()->where('action', 'account.quota.override')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('accounts')
        ->and($entry->target_id)->toBe($account->id)
        ->and($entry->before['quota_bytes'])->toBeNull()
        ->and($entry->after['quota_bytes'])->toBe(1048576);
});

test('clearing the override falls back to the Plan and is audited', function () use ($updateQuota) {
    $plan = Plan::where('slug', 'standard')->firstOrFail();
    $owner = User::factory()->create();
    $account = $owner->account;
    $account->forceFill([
        'plan_id' => $plan->id,
        'quota_bytes' => 1048576,
    ])->save();

    Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    $updateQuota($account->id, ['quota_bytes' => null])
        ->assertOk()
        ->assertJsonPath('quota_bytes', null)
        ->assertJsonPath('effective_quota_bytes', 5368709120)
        ->assertJsonPath('used_bytes', 0);

    expect($account->fresh()->quota_bytes)->toBeNull();

    $entry = AuditLog::query()->where('action', 'account.quota.override')->sole();

    expect($entry->before['quota_bytes'])->toBe(1048576)
        ->and($entry->after['quota_bytes'])->toBeNull();
});

test('a quota must be sent, whole and not negative', function (array $payload) use ($updateQuota) {
    $owner = User::factory()->create();

    $updateQuota($owner->account_id, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quota_bytes');

    expect($owner->account->fresh()->quota_bytes)->toBeNull()
        ->and(AuditLog::query()->where('action', 'account.quota.override')->count())->toBe(0);
})->with([
    'missing' => [[]],
    'negative' => [['quota_bytes' => -1]],
    'not a number' => [['quota_bytes' => '1GB']],
]);

test('the quota endpoint is not reachable without the admin token', function () {
    $owner = User::factory()->create();

    $this->patchJson(route('internal.accounts.quota.update', $owner->account_id), ['quota_bytes' => 1024])
        ->assertNotFound();
});

test('the site token is refused by the quota endpoint', function () {
    $owner = User::factory()->create();

    $this->withHeaders([
        'Authorization' => 'Bearer test-site-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ])->patchJson(route('internal.accounts.quota.update', $owner->account_id), ['quota_bytes' => 1024])
        ->assertNotFound();
});

test('an unknown account is 404', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.accounts.quota.update', Str::uuid()), ['quota_bytes' => 1024])
        ->assertNotFound();
});
