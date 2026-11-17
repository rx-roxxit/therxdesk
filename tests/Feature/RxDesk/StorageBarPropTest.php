<?php

declare(strict_types=1);

use App\Enums\User\Locale;
use App\Models\Account;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Storage\AccountStorage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->mb = 1024 * 1024;
    config(['rxdesk.storage.default_quota' => '5GB']);

    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);
    $this->workspace = Workspace::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
});

test('the owner gets the storage summary on an app page', function () {
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 512 * $this->mb]);

    $this->actingAs($this->owner)
        ->get(route('app.settings.preferences'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('storage.used', 512 * $this->mb)
            ->where('storage.limit', 5 * 1024 * $this->mb)
            ->where('storage.used_label', '512 MB')
            ->where('storage.limit_label', '5 GB')
            ->where('storage.percent', 10));
});

test('the summary caps the percent at 100 when the owner is over the quota', function () {
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 150 * $this->mb]);
    app(AccountStorage::class)->setQuota($this->account, 100 * $this->mb);

    $this->actingAs($this->owner)
        ->get(route('app.settings.preferences'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('storage.percent', 100));
});

test('a member of the account never gets the storage summary', function () {
    $member = User::factory()->create(['account_id' => $this->account->id, 'current_workspace_id' => $this->workspace->id]);
    $this->workspace->members()->attach($member->id, ['is_admin' => false, 'requires_approval' => false]);

    $this->actingAs($member)
        ->get(route('app.settings.preferences'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('storage', null));
});

test('a user whose account owns no workspace gets no storage summary', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('app.settings.preferences'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('storage', null));
});

test('the account menu shows the storage bar and links it to the video policy on preferences', function () {
    expect(file_get_contents(resource_path('js/components/WorkspaceMenuContent.vue')))
        ->toContain('<StorageBar />');

    expect(file_get_contents(resource_path('js/components/StorageBar.vue')))
        ->toContain('data-testid="sidebar-menu-storage"')
        ->toContain("'#video-policy'")
        ->toContain('preferences.url()');
});

test('the storage bar copy is english in every locale', function (string $locale) {
    expect(__('rxdesk.storage_bar.usage', ['used' => '1 GB', 'limit' => '5 GB'], $locale))->toBe('1 GB of 5 GB used');
})->with(Locale::values());
