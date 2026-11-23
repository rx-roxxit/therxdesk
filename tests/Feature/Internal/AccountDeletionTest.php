<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AuditLog;
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

    $this->owner = User::factory()->create();
    $this->account = $this->owner->account;
});

test('the customer detail carries the deletion date', function () {
    $this->account->forceFill(['deletion_scheduled_at' => now()->addDays(9)])->save();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.show', $this->account->id))
        ->assertOk()
        ->assertJsonPath('access_state.deletion_scheduled_at', $this->account->fresh()->deletion_scheduled_at->toIso8601String());
});

test('delete now refuses an email that is not the Owner’s', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.delete', $this->account->id), [
            'confirm_email' => 'someone-else@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('confirm_email');

    expect(Account::find($this->account->id))->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'account.delete_now')->count())->toBe(0);
});

test('delete now keeps the Owner, empty Account and Stripe customer and is audited', function () {
    $this->account->forceFill(['stripe_id' => 'cus_keep', 'first_paid_at' => now()->subMonth()])->save();
    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_keep',
        'stripe_status' => 'active',
        'stripe_price' => 'price_keep',
        'ends_at' => now()->subDay(),
    ]);
    $workspace = Workspace::factory()->create(['account_id' => $this->account->id]);

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.delete', $this->account->id), [
            'confirm_email' => $this->owner->email,
        ])
        ->assertOk()
        ->assertJsonPath('deleted', true)
        ->assertJsonPath('kind', 'purged');

    expect(Workspace::find($workspace->id))->toBeNull()
        ->and(User::find($this->owner->id))->not->toBeNull()
        ->and(Account::find($this->account->id))->not->toBeNull();

    $entry = AuditLog::query()->where('action', 'account.delete_now')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_id)->toBe($this->account->id)
        ->and($entry->after['result'])->toBe('purged');
});

test('delete now removes a never-paid signup entirely', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.delete', $this->account->id), [
            'confirm_email' => $this->owner->email,
        ])
        ->assertOk()
        ->assertJsonPath('kind', 'never_paid');

    expect(Account::find($this->account->id))->toBeNull()
        ->and(User::find($this->owner->id))->toBeNull();
});

test('delete now is not reachable without the admin token', function () {
    $this->postJson(route('internal.accounts.delete', $this->account->id), [
        'confirm_email' => $this->owner->email,
    ])->assertNotFound();

    expect(Account::find($this->account->id))->not->toBeNull();
});

test('an unknown account is 404', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.accounts.delete', Str::uuid()), [
            'confirm_email' => 'anyone@example.com',
        ])
        ->assertNotFound();
});
