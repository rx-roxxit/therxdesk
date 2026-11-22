<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeDiskSpace;

const MEDIA_ON_R2_URL = 'https://media.therxdesk.test';

beforeEach(function () {
    config(['filesystems.default' => 'r2']);
    Storage::fake('r2', ['url' => MEDIA_ON_R2_URL]);
    config([
        'rxdesk.storage.default_quota' => '10MB',
        'rxdesk.storage.disk_guard_threshold' => '10GB',
    ]);

    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);
    $this->workspace = Workspace::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    subscribeAccount($this->account);
});

/**
 * The first chunk of a file whose declared size is `$totalSize`.
 */
function startR2DiskGuardUpload(User $user, int $totalSize): TestResponse
{
    return test()->actingAs($user)->call(
        'POST',
        route('app.media.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-1023/'.$totalSize,
            'HTTP_X_FILE_NAME' => 'clip.mp4',
            'HTTP_X_UPLOAD_ID' => Str::uuid()->toString(),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        str_repeat('x', 1024),
    );
}

test('a stored media row serves its file from the R2 public domain', function () {
    $channel = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);
    $post = Post::factory()->forAccount($channel)->create(['user_id' => $this->owner->id]);
    $media = Media::factory()->ownedByPost($post)->stored()->create();
    $post->update(['media' => [$media->toArray()]]);

    expect($media->url)->toStartWith(MEDIA_ON_R2_URL.'/')
        ->and(data_get($post->refresh()->media, '0.url'))->toStartWith(MEDIA_ON_R2_URL.'/');
});

test('the Disk guard still refuses a chunked upload while media is on R2', function () {
    FakeDiskSpace::bind(free: 1024 ** 3);

    startR2DiskGuardUpload($this->owner, 1024 * 1024)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['total_size' => "The server is out of space, so this file can't be added. Ask the admin to free some space, then try again."]);

    expect(Media::query()->count())->toBe(0);
});
