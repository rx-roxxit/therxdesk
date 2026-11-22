<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Media;
use App\Models\Plan;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Storage\AccountStorage;

beforeEach(function () {
    $this->mb = 1024 * 1024;
    config(['rxdesk.storage.default_quota' => '5GB']);

    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);
    $this->workspace = Workspace::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
});

test('storage used sums the media of every workspace of the account', function () {
    $secondWorkspace = Workspace::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
    $post = Post::factory()->create(['workspace_id' => $secondWorkspace->id, 'user_id' => $this->owner->id]);

    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 3 * $this->mb]);
    Media::factory()->ownedByPost($post)->video()->create(['size' => 40 * $this->mb]);

    $usage = app(AccountStorage::class)->usage($this->account);

    expect($usage->used)->toBe(43 * $this->mb)
        ->and($usage->limit)->toBe(5 * 1024 * $this->mb);
});

test('storage used leaves out avatars, workspace logos and other accounts', function () {
    $otherWorkspace = Workspace::factory()->create();

    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 2 * $this->mb]);
    Media::factory()->logo()->create(['mediable_id' => $this->workspace->id, 'size' => 1 * $this->mb]);
    Media::factory()->avatar()->create(['mediable_type' => $this->owner->getMorphClass(), 'mediable_id' => $this->owner->id, 'size' => 1 * $this->mb]);
    Media::factory()->temporaryUpload($otherWorkspace)->create(['size' => 9 * $this->mb]);

    expect(app(AccountStorage::class)->usage($this->account)->used)->toBe(2 * $this->mb);
});

test("an account's own Quota replaces the default", function () {
    $this->account->forceFill(['quota_bytes' => 20 * 1024 * $this->mb])->save();

    expect(app(AccountStorage::class)->usage($this->account)->limit)->toBe(20 * 1024 * $this->mb);
});

test('the default Quota comes from env', function () {
    config(['rxdesk.storage.default_quota' => '750MB']);

    expect(app(AccountStorage::class)->usage($this->account)->limit)->toBe(750 * $this->mb);
});

test('an Account on a Plan uses the Plan Quota when it has no override', function () {
    $plan = Plan::factory()->create(['quota_bytes' => 2 * 1024 * $this->mb]);
    $this->account->update(['plan_id' => $plan->id]);

    expect(app(AccountStorage::class)->limit($this->account))->toBe(2 * 1024 * $this->mb);
});

test('an Account override wins over its Plan Quota', function () {
    $plan = Plan::factory()->create(['quota_bytes' => 2 * 1024 * $this->mb]);
    $this->account->update(['plan_id' => $plan->id]);
    $this->account->forceFill(['quota_bytes' => 8 * 1024 * $this->mb])->save();

    expect(app(AccountStorage::class)->limit($this->account))->toBe(8 * 1024 * $this->mb);
});

test('an Account whose Plan has no Quota falls back to the env default', function () {
    $plan = Plan::factory()->create(['quota_bytes' => null]);
    $this->account->update(['plan_id' => $plan->id]);

    expect(app(AccountStorage::class)->limit($this->account))->toBe(5 * 1024 * $this->mb);
});

test('an account can store bytes up to its Quota and no further', function () {
    config(['rxdesk.storage.default_quota' => '10MB']);
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 6 * $this->mb]);

    $storage = app(AccountStorage::class);

    expect($storage->canStore($this->account, 4 * $this->mb))->toBeTrue()
        ->and($storage->canStore($this->account, 4 * $this->mb + 1))->toBeFalse();
});
