<?php

declare(strict_types=1);

use App\Actions\Audit\RecordAuditEntry;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

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
    $this->siteHeaders = ['Authorization' => 'Bearer test-site-token'];
});

test('the audit log lists entries newest first with before and after', function () {
    $account = Account::factory()->create();

    RecordAuditEntry::execute('first@example.com', 'account.quota.set', $account, ['quota_bytes' => 1], ['quota_bytes' => 2]);
    RecordAuditEntry::execute('second@example.com', 'account.quota.set', $account, ['quota_bytes' => 2], ['quota_bytes' => 3]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.audit-log.index'));

    $response->assertOk()
        ->assertJsonPath('data.0.actor', 'second@example.com')
        ->assertJsonPath('data.0.action', 'account.quota.set')
        ->assertJsonPath('data.0.target_type', 'accounts')
        ->assertJsonPath('data.0.target_id', $account->id)
        ->assertJsonPath('data.0.before.quota_bytes', 2)
        ->assertJsonPath('data.0.after.quota_bytes', 3)
        ->assertJsonPath('data.1.actor', 'first@example.com');
});

test('the site token cannot read the audit log', function () {
    $this->withHeaders($this->siteHeaders)
        ->getJson(route('internal.audit-log.index'))
        ->assertNotFound();
});

test('a write records who, what, the target and before and after without secrets', function () {
    $user = User::factory()->create();

    $entry = RecordAuditEntry::execute(
        'admin@example.com',
        'user.update',
        $user,
        [
            'name' => 'Old',
            'password' => 'old-secret',
            'tokens_per_credit' => 150,
            'nested' => ['password_confirmation' => 'confirmed-secret', 'api_key' => 'key-secret'],
        ],
        ['name' => 'New', 'password' => 'new-secret'],
    );

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->action)->toBe('user.update')
        ->and($entry->target_type)->toBe('users')
        ->and($entry->target_id)->toBe($user->id)
        ->and($entry->before)->toBe(['name' => 'Old', 'tokens_per_credit' => 150, 'nested' => []])
        ->and($entry->after)->toBe(['name' => 'New']);

    $row = DB::table('audit_logs')->where('id', $entry->id)->first();

    expect($row->before)->toBeString()
        ->and($row->before)->not->toContain('secret')
        ->and($row->before)->not->toContain('api_key')
        ->and($row->after)->not->toContain('secret');
});

test('a CLI Quota change is audited as cli', function () {
    $owner = User::factory()->create(['email' => 'ann@example.com']);
    Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);

    $this->artisan('rxdesk:quota', [
        'action' => 'set',
        'email' => 'ann@example.com',
        'size' => '20GB',
    ])->assertExitCode(0);

    $entry = AuditLog::query()->latest('created_at')->firstOrFail();

    expect($entry->actor)->toBe('cli')
        ->and($entry->action)->toBe('account.quota.set')
        ->and($entry->target_type)->toBe('accounts')
        ->and($entry->target_id)->toBe($owner->account->id)
        ->and($entry->before)->toBe(['quota_bytes' => null])
        ->and($entry->after)->toBe(['quota_bytes' => 20 * 1024 ** 3]);
});

test('setting the default Quota audits the null after value', function () {
    $owner = User::factory()->create(['email' => 'ann@example.com']);
    $owner->account->forceFill(['quota_bytes' => 1024])->save();

    Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);

    $this->artisan('rxdesk:quota', [
        'action' => 'set',
        'email' => 'ann@example.com',
        'size' => 'default',
    ])->assertExitCode(0);

    $entry = AuditLog::query()->latest('created_at')->firstOrFail();

    expect($entry->before)->toBe(['quota_bytes' => 1024])
        ->and($entry->after)->toBe(['quota_bytes' => null]);
});
