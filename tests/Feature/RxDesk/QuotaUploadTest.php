<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\ChunkedAssetReceiver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

const QUOTA_FULL_OWNER = "You're out of storage: you've used 9 MB of your 10 MB. Delete posts or media you no longer need, then try again.";

const QUOTA_FULL_MEMBER = "The workspace owner's storage is full, so this file can't be added. Ask them to free some space, then try again.";

beforeEach(function () {
    Storage::fake();
    fakePublicDns();
    config(['rxdesk.storage.default_quota' => '10MB']);

    $this->mb = 1024 * 1024;
    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);
    $this->workspace = Workspace::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    subscribeAccount($this->account);

    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 9 * $this->mb]);
});

/**
 * The first chunk of a file whose declared size is `$totalSize`.
 */
function startQuotaUpload(User $user, int $totalSize, string $fileName = 'clip.mp4'): TestResponse
{
    $chunk = str_repeat('x', 1024);

    return test()->actingAs($user)->call(
        'POST',
        route('app.media.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-1023/'.$totalSize,
            'HTTP_X_FILE_NAME' => $fileName,
            'HTTP_X_UPLOAD_ID' => Str::uuid()->toString(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $chunk,
    );
}

/**
 * @return list<string>
 */
function quotaChunkFiles(): array
{
    return File::isDirectory(ChunkedAssetReceiver::chunkDirectory())
        ? File::files(ChunkedAssetReceiver::chunkDirectory())
        : [];
}

test('an upload whose declared size passes the Quota is refused before any bytes land', function () {
    $chunksBefore = quotaChunkFiles();

    startQuotaUpload($this->owner, 2 * $this->mb)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['total_size' => QUOTA_FULL_OWNER]);

    expect(Media::query()->count())->toBe(1)
        ->and(quotaChunkFiles())->toEqual($chunksBefore)
        ->and(Storage::allFiles())->toBe([]);
});

test('an upload that fits the Quota starts', function () {
    startQuotaUpload($this->owner, 1 * $this->mb)
        ->assertOk()
        ->assertJson(['done' => false]);
});

test("a Member is told the workspace owner's storage is full", function () {
    $member = workspaceMember($this->workspace);

    startQuotaUpload($member, 2 * $this->mb)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['total_size' => QUOTA_FULL_MEMBER]);
});
