<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Media;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

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

test('storage paginates with the configured page size', function () {
    for ($i = 0; $i < 30; $i++) {
        $owner = User::factory()->create();
        Workspace::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
        ]);
    }

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.storage.index'))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonPath('meta.total', 30)
        ->assertJsonCount(25, 'data');
});

test('the per_page query is ignored', function () {
    for ($i = 0; $i < 3; $i++) {
        $owner = User::factory()->create();
        Workspace::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
        ]);
    }

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.storage.index', ['per_page' => 1]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonCount(3, 'data');
});

test('a row carries the account, owner, used bytes, quota and percent', function () {
    $owner = User::factory()->create(['email' => 'storage@example.com']);
    $account = $owner->account;
    $account->update(['name' => 'Storage Co']);
    $account->forceFill(['quota_bytes' => 1048576])->save();

    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    Media::factory()->temporaryUpload($workspace)->create(['size' => 2048]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.storage.index'))
        ->assertOk()
        ->assertJsonPath('data.0.account.id', $account->id)
        ->assertJsonPath('data.0.account.name', 'Storage Co')
        ->assertJsonPath('data.0.owner.email', 'storage@example.com')
        ->assertJsonPath('data.0.used_bytes', 2048)
        ->assertJsonPath('data.0.quota_bytes', 1048576)
        ->assertJsonPath('data.0.percent', 0.2);
});

test('the quota falls back to the Plan when the Account has no override', function () {
    $owner = User::factory()->create();
    $owner->account->forceFill([
        'plan_id' => Plan::where('slug', 'standard')->firstOrFail()->id,
    ])->save();

    Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.storage.index'))
        ->assertOk()
        ->assertJsonPath('data.0.quota_bytes', 5368709120);
});

test('a zero quota reports a zero percent', function () {
    $owner = User::factory()->create();
    $owner->account->forceFill(['quota_bytes' => 0])->save();

    Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.storage.index'))
        ->assertOk()
        ->assertJsonPath('data.0.quota_bytes', 0)
        ->assertJsonPath('data.0.percent', 0);
});

test('accounts are ordered by name', function () {
    foreach (['Zeta', 'Alpha', 'Beta'] as $name) {
        $owner = User::factory()->create();
        $owner->account->update(['name' => $name]);
        Workspace::factory()->create([
            'account_id' => $owner->account_id,
            'user_id' => $owner->id,
        ]);
    }

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.storage.index'))
        ->assertOk()
        ->assertJsonPath('data.0.account.name', 'Alpha')
        ->assertJsonPath('data.1.account.name', 'Beta')
        ->assertJsonPath('data.2.account.name', 'Zeta');
});

test('accounts without an owner or a workspace are left out', function () {
    Account::factory()->create(['owner_id' => null]);
    User::factory()->create();

    $owner = User::factory()->create();
    Workspace::factory()->create([
        'account_id' => $owner->account_id,
        'user_id' => $owner->id,
    ]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.storage.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.account.id', $owner->account_id);
});

test('the storage endpoint is not reachable without the admin token', function () {
    $this->getJson(route('internal.storage.index'))
        ->assertNotFound();
});

test('the site token is refused by the storage endpoint', function () {
    $this->withHeaders([
        'Authorization' => 'Bearer test-site-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ])->getJson(route('internal.storage.index'))
        ->assertNotFound();
});
