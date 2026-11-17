<?php

declare(strict_types=1);

use App\Enums\Post\PublishStatus;
use App\Enums\Post\RecurrenceFrequency;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\Storage\StorageRefusal;
use App\Jobs\Media\ImportRemoteMedia;
use App\Jobs\PublishToSocialPlatform;
use App\Models\Account;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Http\HostResolver;
use App\Services\Media\ChunkedAssetReceiver;
use App\Services\Media\RemoteMediaImporter;
use App\Services\Storage\AccountStorage;
use App\Services\Storage\DiskSpace;
use App\Services\Storage\LocalDiskSpace;
use App\Support\MediaImportStatus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeDiskSpace;

const DISK_GUARD_MESSAGE = "The server is out of space, so this file can't be added. Ask the admin to free some space, then try again.";

beforeEach(function () {
    Storage::fake();
    fakePublicDns();
    config([
        'rxdesk.storage.default_quota' => '10MB',
        'rxdesk.storage.disk_guard_threshold' => '10GB',
    ]);

    $this->gb = 1024 ** 3;
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
function startDiskGuardUpload(User $user, int $totalSize): TestResponse
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

/**
 * @return array<string, mixed>
 */
function diskGuardDrivePick(): array
{
    return [
        'source' => 'google_drive',
        'access_token' => 'ya29.drive-file-only-token',
        'file' => ['id' => '1AbC_dE-fGh', 'name' => 'beach.png'],
    ];
}

test('under the threshold an account is refused with the Disk guard even with Quota room', function () {
    FakeDiskSpace::bind(free: 10 * $this->gb - 1);

    $storage = app(AccountStorage::class);

    expect($storage->refusal($this->account, 1))->toBe(StorageRefusal::DiskGuard)
        ->and($storage->canStore($this->account, 1))->toBeFalse()
        ->and($storage->diskStatus()->isGuarding())->toBeTrue();
});

test('the Disk guard wins over the Quota when both refuse', function () {
    FakeDiskSpace::bind(free: 1 * $this->gb);
    $this->account->forceFill(['quota_bytes' => 10])->save();

    expect(app(AccountStorage::class)->refusal($this->account, 1000))->toBe(StorageRefusal::DiskGuard);
});

test('at or above the threshold the Quota rules decide as before', function () {
    FakeDiskSpace::bind(free: 10 * $this->gb);
    $storage = app(AccountStorage::class);

    expect($storage->refusal($this->account, 1024))->toBeNull()
        ->and($storage->diskStatus()->isGuarding())->toBeFalse()
        ->and($storage->refusal($this->account, 11 * 1024 * 1024))->toBe(StorageRefusal::Quota);
});

test('the threshold comes from the env setting', function () {
    config(['rxdesk.storage.disk_guard_threshold' => '50GB']);
    FakeDiskSpace::bind(free: 40 * $this->gb);

    expect(app(AccountStorage::class)->refusal($this->account, 1))->toBe(StorageRefusal::DiskGuard);
});

test('an upload is refused before any bytes land, with the same message for the Owner and a Member', function (string $access) {
    FakeDiskSpace::bind(free: 1 * $this->gb);
    $user = $access === 'owner' ? $this->owner : workspaceMember($this->workspace);
    $chunkDirectory = ChunkedAssetReceiver::chunkDirectory();
    $chunksBefore = File::isDirectory($chunkDirectory) ? File::files($chunkDirectory) : [];

    startDiskGuardUpload($user, 1024 * 1024)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['total_size' => DISK_GUARD_MESSAGE]);

    $chunksAfter = File::isDirectory($chunkDirectory) ? File::files($chunkDirectory) : [];

    expect(Media::query()->count())->toBe(0)
        ->and($chunksAfter)->toEqual($chunksBefore)
        ->and(Storage::allFiles())->toBe([]);
})->with(['owner', 'member']);

test('an upload that fits the Quota starts while the disk has room', function () {
    FakeDiskSpace::bind(free: 10 * $this->gb);

    startDiskGuardUpload($this->owner, 1024 * 1024)->assertOk()->assertJson(['done' => false]);
});

test('an import is refused at the start, for the Owner and a Member alike', function (string $access) {
    FakeDiskSpace::bind(free: 1 * $this->gb);
    $user = $access === 'owner' ? $this->owner : workspaceMember($this->workspace);
    config()->set([
        'services.google_media.client_id' => 'media-client-id',
        'services.google_media.client_secret' => 'media-client-secret',
        'services.google_media.redirect' => 'https://trypost.example/integrations/google/callback',
        'services.google_media.api_key' => 'media-api-key',
        'services.google_media.app_id' => '123456789012',
        'trypost.media_sources.google_drive.enabled' => true,
    ]);
    Queue::fake();

    $this->actingAs($user)
        ->postJson(route('app.media.imports.store'), diskGuardDrivePick())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source' => DISK_GUARD_MESSAGE]);

    Queue::assertNotPushed(ImportRemoteMedia::class);
    expect(Media::query()->count())->toBe(0)
        ->and(Storage::allFiles())->toBe([]);
})->with(['owner', 'member']);

test('a queued import that finds the disk full records server_out_of_space and stores nothing', function (string $access) {
    config()->set([
        'services.google_media.client_id' => 'media-client-id',
        'services.google_media.client_secret' => 'media-client-secret',
        'services.google_media.redirect' => 'https://trypost.example/integrations/google/callback',
        'services.google_media.api_key' => 'media-api-key',
        'services.google_media.app_id' => '123456789012',
        'trypost.media_sources.google_drive.enabled' => true,
    ]);
    $this->mock(HostResolver::class)->shouldReceive('addresses')->andReturn(['142.250.0.10']);
    Http::fake([
        config('trypost.media_sources.google_drive.api').'/files/*' => Http::response(file_get_contents(base_path('tests/fixtures/1x1.png')), 200, ['Content-Type' => 'image/png']),
    ]);
    $user = $access === 'owner' ? $this->owner : workspaceMember($this->workspace);
    Queue::fake();

    $importId = $this->actingAs($user)
        ->postJson(route('app.media.imports.store'), diskGuardDrivePick())
        ->assertAccepted()
        ->json('import_id');

    FakeDiskSpace::bind(free: 1 * $this->gb);
    Queue::pushed(ImportRemoteMedia::class)->sole()->handle(app(RemoteMediaImporter::class));

    $this->actingAs($user)
        ->getJson(route('app.media.imports.show', $importId))
        ->assertOk()
        ->assertJsonPath('status', MediaImportStatus::FAILED)
        ->assertJsonPath('reason', 'server_out_of_space');

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::allFiles())->toBe([]);
})->with(['owner', 'member']);

test('a new media row is refused under the threshold, and its stored file goes', function () {
    FakeDiskSpace::bind(free: 1 * $this->gb);
    Storage::put('stray/file.png', 'x');

    expect(fn () => Media::factory()->temporaryUpload($this->workspace)->create(['path' => 'stray/file.png', 'size' => 1]))
        ->toThrow(App\Exceptions\Storage\StorageRefused::class);

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::exists('stray/file.png'))->toBeFalse();
});

test('a scheduled post still publishes, and its next occurrence is copied, while the Disk guard is active', function () {
    $this->freezeTime();

    $channel = SocialAccount::factory()->create([
        'platform' => Platform::Facebook,
        'scopes' => Platform::Facebook->requiredPublishScopes(),
        'timezone' => 'UTC',
    ]);
    $post = Post::factory()->forAccount($channel, ContentType::defaultFor(Platform::Facebook))->scheduled()->create([
        'user_id' => $channel->workspace->user_id,
        'scheduled_at' => now()->subMinute(),
        'content' => 'Weekly photo',
        'recurrence_frequency' => RecurrenceFrequency::Week,
        'recurrence_interval' => 1,
        'recurrence_remaining' => 3,
    ]);
    $photo = Media::factory()->ownedByPost($post)->stored()->create(['size' => 100000, 'meta' => ['width' => 1080, 'height' => 1080]]);
    $post->update(['media' => [$photo->toArray()]]);

    FakeDiskSpace::bind(free: 1 * $this->gb);
    expect(app(AccountStorage::class)->canStore($channel->workspace->account, 1))->toBeFalse();

    Http::fake([
        config('trypost.platforms.facebook.graph_api')."/{$channel->platform_user_id}/photos" => Http::response(['id' => 'photo-123']),
    ]);

    (new PublishToSocialPlatform($post->fresh()))->handle();

    $nextOccurrence = Post::query()->whereKeyNot($post->id)->sole();

    expect($post->refresh()->status)->toBe(PostStatus::Published)
        ->and($post->publish_status)->toBe(PublishStatus::Published)
        ->and($photo->fresh())->not->toBeNull()
        ->and($nextOccurrence->status)->toBe(PostStatus::Scheduled)
        ->and(Media::query()->where('post_id', $nextOccurrence->id)->count())->toBe(1);
});

test('the container reads the real disk when no fake is bound', function () {
    app()->forgetInstance(DiskSpace::class);

    $disk = app(DiskSpace::class);

    expect($disk)->toBeInstanceOf(LocalDiskSpace::class)
        ->and($disk->total())->toBeGreaterThan(0)
        ->and($disk->free())->toBeLessThanOrEqual($disk->total());
});
