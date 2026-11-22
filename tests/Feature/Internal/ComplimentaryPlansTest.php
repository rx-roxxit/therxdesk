<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\Storage\AccountStorage;
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

    $this->standard = Plan::where('slug', 'standard')->firstOrFail();
    $this->account = Account::factory()->create(['trial_ends_at' => null]);
});

$give = function (array $payload = []): TestResponse {
    return test()->withHeaders(test()->adminHeaders)
        ->postJson(route('internal.accounts.complimentary.store', test()->account->id), [
            'plan_id' => test()->standard->id,
            ...$payload,
        ]);
};

test('giving a Plan grants access, writes the Plan and records an audit row', function () use ($give) {
    $response = $give();

    $response->assertOk()
        ->assertJsonPath('id', $this->account->id)
        ->assertJsonPath('plan.slug', 'standard')
        ->assertJsonPath('complimentary.in_force', true)
        ->assertJsonPath('complimentary.until', null);

    $account = $this->account->fresh();

    expect($account->plan_id)->toBe($this->standard->id)
        ->and($account->complimentary_since)->not->toBeNull()
        ->and($account->complimentary_until)->toBeNull()
        ->and($account->hasAppAccess())->toBeTrue()
        ->and($account->isComplimentary())->toBeTrue();

    $entry = AuditLog::query()->where('action', 'account.complimentary.give')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('accounts')
        ->and($entry->target_id)->toBe($this->account->id)
        ->and($entry->before['plan_id'])->toBeNull()
        ->and($entry->after['plan_id'])->toBe($this->standard->id)
        ->and($entry->after['complimentary_since'])->not->toBeNull();
});

test('the Quota and AI credits come from the given Plan', function () use ($give) {
    $give()->assertOk();

    $account = $this->account->fresh();

    expect(app(AccountStorage::class)->limit($account))->toBe($this->standard->quota_bytes)
        ->and($account->aiCreditsPerMonth())->toBe($this->standard->ai_credits_per_month);
});

test('an archived Plan can be given', function () {
    $archived = Plan::factory()->archived()->create([
        'slug' => 'legacy',
        'name' => 'Legacy',
        'quota_bytes' => 1073741824,
        'ai_credits_per_month' => 100,
    ]);

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.complimentary.store', $this->account->id), [
            'plan_id' => $archived->id,
        ])
        ->assertOk()
        ->assertJsonPath('plan.slug', 'legacy');

    expect($this->account->fresh()->plan_id)->toBe($archived->id);
});

test('giving a Plan stores its end date', function () use ($give) {
    $this->travelTo(now()->startOfSecond());
    $endsAt = now()->addDays(10);

    $give(['ends_at' => $endsAt->toIso8601String()])->assertOk();

    expect($this->account->fresh()->complimentary_until->equalTo($endsAt))->toBeTrue();
});

test('giving again while one is in force updates the Plan and date and is audited', function () use ($give) {
    $give()->assertOk();

    $plus = Plan::where('slug', 'plus')->firstOrFail();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.complimentary.store', $this->account->id), [
            'plan_id' => $plus->id,
            'ends_at' => now()->addDays(3)->toIso8601String(),
        ])
        ->assertOk()
        ->assertJsonPath('plan.slug', 'plus');

    $account = $this->account->fresh();

    expect($account->plan_id)->toBe($plus->id)
        ->and(AuditLog::query()->where('action', 'account.complimentary.give')->count())->toBe(2);
});

test('a blocked Account is refused and nothing changes', function () use ($give) {
    $this->account->forceFill(['blocked_country_at' => now()])->save();

    $give()->assertUnprocessable()->assertJsonValidationErrors('account');

    $account = $this->account->fresh();

    expect($account->plan_id)->toBeNull()
        ->and($account->complimentary_since)->toBeNull()
        ->and(AuditLog::query()->where('action', 'account.complimentary.give')->count())->toBe(0);
});

test('an Account with an active Subscription is refused and nothing changes', function () use ($give) {
    subscribeAccount($this->account);

    $give()->assertUnprocessable()->assertJsonValidationErrors('account');

    $account = $this->account->fresh();

    expect($account->plan_id)->toBeNull()
        ->and($account->complimentary_since)->toBeNull()
        ->and(AuditLog::query()->where('action', 'account.complimentary.give')->count())->toBe(0);
});

test('ending without a date sets 30 days ahead and records an audit row', function () use ($give) {
    $this->travelTo(now()->startOfSecond());
    $give()->assertOk();

    $response = $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.complimentary.end', $this->account->id));

    $response->assertOk()->assertJsonPath('complimentary.in_force', true);

    expect($this->account->fresh()->complimentary_until->equalTo(now()->addDays(30)))->toBeTrue();

    $entry = AuditLog::query()->where('action', 'account.complimentary.end')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->after['complimentary_until'])->not->toBeNull();
});

test('ending accepts an earlier date and lapses the Account at once', function () use ($give) {
    $give()->assertOk();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.complimentary.end', $this->account->id), [
            'ends_at' => now()->subMinute()->toIso8601String(),
        ])
        ->assertOk()
        ->assertJsonPath('complimentary.in_force', false);

    $account = $this->account->fresh();

    expect($account->isComplimentary())->toBeFalse()
        ->and($account->hasAppAccess())->toBeFalse()
        ->and($account->isLapsed())->toBeTrue();
});

test('an end date past the timestamp ceiling is refused and the sentinel is accepted', function () use ($give) {
    $give()->assertOk();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.complimentary.end', $this->account->id), [
            'ends_at' => '2038-01-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ends_at');

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.complimentary.end', $this->account->id), [
            'ends_at' => '2037-12-31',
        ])
        ->assertOk();

    expect($this->account->fresh()->complimentary_until->toDateString())->toBe('2037-12-31');
});

test('ending an Account that never had a Complimentary Plan is refused', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.complimentary.end', $this->account->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account');

    expect($this->account->fresh()->complimentary_since)->toBeNull()
        ->and(AuditLog::query()->where('action', 'account.complimentary.end')->count())->toBe(0);
});
