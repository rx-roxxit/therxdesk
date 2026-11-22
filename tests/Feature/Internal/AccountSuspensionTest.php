<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AuditLog;
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

    $this->account = Account::factory()->create(['trial_ends_at' => null]);
});

$suspend = function (array $payload = []): TestResponse {
    return test()->withHeaders(test()->adminHeaders)
        ->postJson(route('internal.accounts.suspension.store', test()->account->id), [
            'reason' => 'Chargeback investigation',
            ...$payload,
        ]);
};

test('a suspension writes the switch and records an audit row', function () use ($suspend) {
    $response = $suspend();

    $response->assertOk()
        ->assertJsonPath('id', $this->account->id)
        ->assertJsonPath('suspended', true)
        ->assertJsonPath('reason', 'Chargeback investigation')
        ->assertJsonPath('has_app_access', false);

    $account = $this->account->fresh();

    expect($account->isSuspended())->toBeTrue()
        ->and($account->suspended_at)->not->toBeNull()
        ->and($account->suspension_reason)->toBe('Chargeback investigation');

    $entry = AuditLog::query()->where('action', 'account.suspend')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('accounts')
        ->and($entry->target_id)->toBe($this->account->id)
        ->and($entry->before['suspended_at'])->toBeNull()
        ->and($entry->after['suspended_at'])->not->toBeNull()
        ->and($entry->after['suspension_reason'])->toBe('Chargeback investigation');
});

test('suspending again updates the reason', function () use ($suspend) {
    $suspend();
    $suspend(['reason' => 'Repeat chargeback']);

    expect($this->account->fresh()->suspension_reason)->toBe('Repeat chargeback')
        ->and(AuditLog::query()->where('action', 'account.suspend')->count())->toBe(2);
});

test('a suspension needs a reason of a useful length', function (array $payload) use ($suspend) {
    $suspend($payload)->assertUnprocessable()->assertJsonValidationErrors('reason');

    expect($this->account->fresh()->isSuspended())->toBeFalse();
})->with([
    'missing' => [['reason' => null]],
    'too short' => [['reason' => 'no']],
    'too long' => [['reason' => str_repeat('a', 256)]],
]);

test('an unknown account is 404', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.suspension.store', Str::uuid()), [
            'reason' => 'Chargeback investigation',
        ])
        ->assertNotFound();
});

test('the suspension endpoint is not reachable without the admin token', function () {
    $this->postJson(route('internal.accounts.suspension.store', $this->account->id), [
        'reason' => 'Chargeback investigation',
    ])->assertNotFound();

    expect($this->account->fresh()->isSuspended())->toBeFalse();
});

test('lifting restores access, clears the reason and records an audit row', function () use ($suspend) {
    $suspend();

    $response = $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.suspension.lift', $this->account->id));

    $response->assertOk()
        ->assertJsonPath('suspended', false)
        ->assertJsonPath('suspended_at', null)
        ->assertJsonPath('reason', null);

    $account = $this->account->fresh();

    expect($account->isSuspended())->toBeFalse()
        ->and($account->suspended_at)->toBeNull()
        ->and($account->suspension_reason)->toBeNull();

    $entry = AuditLog::query()->where('action', 'account.lift')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->before['suspended_at'])->not->toBeNull()
        ->and($entry->before['suspension_reason'])->toBe('Chargeback investigation')
        ->and($entry->after['suspended_at'])->toBeNull()
        ->and($entry->after['suspension_reason'])->toBeNull();
});

test('lifting an account that is not suspended is refused', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.suspension.lift', $this->account->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('account');

    expect(AuditLog::query()->where('action', 'account.lift')->count())->toBe(0);
});

test('the suspension reason never reaches the app shared props or the public API', function () use ($suspend) {
    $suspend();

    $owner = User::factory()->create(['account_id' => $this->account->id]);

    $this->actingAs($owner)
        ->get(route('app.welcome.suspended'))
        ->assertOk()
        ->assertDontSee('Chargeback investigation');

    $api = createApiTestToken(['workspace' => Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $owner->id,
    ])]);

    $this->withHeaders(['Authorization' => 'Bearer '.$api['plain_token']])
        ->getJson(route('api.workspace.show'))
        ->assertPaymentRequired()
        ->assertDontSee('Chargeback investigation');
});
