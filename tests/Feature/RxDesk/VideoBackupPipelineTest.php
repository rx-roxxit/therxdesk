<?php

declare(strict_types=1);

use App\Enums\Account\VideoPolicy;
use App\Enums\Media\Source;
use App\Enums\Post\PublishStatus;
use App\Enums\Post\Status as PostStatus;
use App\Enums\VideoRecord\Outcome;
use App\Jobs\Media\DeleteMediaFiles;
use App\Jobs\VideoPolicy\ApplyVideoPolicy;
use App\Models\Media;
use App\Models\MediaSourceConnection;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\VideoRecord;
use App\Models\Workspace;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

const BACKUP_TEST_DRIVE_API = 'https://drive.google.test/drive/v3';

const BACKUP_TEST_UPLOAD_API = 'https://drive.google.test/upload/drive/v3';

const BACKUP_TEST_TOKEN_API = 'https://oauth.google.test';

const BACKUP_TEST_FOLDER_MIME = 'application/vnd.google-apps.folder';

beforeEach(function () {
    $this->freezeTime();
    Storage::fake();
    config()->set([
        'queue.default' => 'database',
        'services.google_media.client_id' => 'media-client-id',
        'services.google_media.client_secret' => 'media-client-secret',
        'trypost.media_sources.google_oauth.token_api' => BACKUP_TEST_TOKEN_API,
        'trypost.media_sources.google_drive.api' => BACKUP_TEST_DRIVE_API,
        'trypost.media_sources.google_drive.upload_api' => BACKUP_TEST_UPLOAD_API,
    ]);

    Queue::swap(Queue::getFacadeRoot()->queue);
    $this->queue = Queue::connection('database');
    $this->worker = app('queue.worker');
    Bus::fake()->except([ApplyVideoPolicy::class, DeleteMediaFiles::class]);
    $this->workerOptions = new WorkerOptions(sleep: 0, maxTries: 1);

    $this->workspace = Workspace::factory()->create(['name' => 'Acme Store']);
    $this->account = $this->workspace->account;
    $this->account->forceFill(['video_policy' => VideoPolicy::Backup])->save();
    $this->account->owner->forceFill(['timezone' => 'America/Sao_Paulo'])->save();
    $this->connection = MediaSourceConnection::factory()->create([
        'user_id' => $this->account->owner_id,
        'source' => Source::GoogleDriveBackup,
        'access_token' => 'drive-access',
    ]);
    $this->groupId = (string) Str::uuid7();
});

/**
 * A post of the shared group, scheduled on its own Telegram channel, whose
 * video is a copy of `$copyOf` (the group's original upload) when given.
 *
 * @return array{post: Post, video: Media}
 */
function backupGroupPost(string $channel, ?Media $copyOf = null, string $contents = 'video bytes'): array
{
    $account = SocialAccount::factory()->telegram()->create([
        'workspace_id' => test()->workspace->id,
        'username' => $channel,
    ]);
    $post = Post::factory()->forAccount($account)->scheduled()->create([
        'user_id' => test()->account->owner_id,
        'post_group_id' => test()->groupId,
    ]);
    $video = Media::factory()->ownedByPost($post)->video()->create([
        'original_filename' => 'launch.mp4',
        'size' => strlen($contents),
        'meta' => $copyOf === null ? ['duration' => 15] : ['duration' => 15, 'copied_from' => $copyOf->id],
    ]);
    Storage::put($video->path, $contents);
    $post->update(['media' => [$video->toArray()]]);

    return ['post' => $post->fresh(), 'video' => $video];
}

function markBackupPostPublished(Post $post): void
{
    $post->forceFill([
        'status' => PostStatus::Published,
        'publish_status' => PublishStatus::Published,
        'published_at' => now(),
    ])->save();
}

/**
 * Runs every job due on the default queue: the policy jobs, then the file
 * deletions they queue.
 */
function runDueBackupJobs(): void
{
    while (test()->queue->pendingSize('default') > 0) {
        test()->worker->runNextJob('database', 'default', test()->workerOptions);
    }
}

/**
 * A Drive that keeps the folders it was asked to create, answers resumable
 * sessions chunk by chunk, and fails uploads while `failUploads` is set.
 */
function fakeDrive(): stdClass
{
    $drive = new stdClass;
    $drive->folders = [];
    $drive->sessions = [];
    $drive->failUploads = false;

    Http::fake(function (Request $request) use ($drive) {
        $url = $request->url();

        if (Str::startsWith($url, BACKUP_TEST_TOKEN_API)) {
            return Http::response(['access_token' => 'drive-refreshed', 'expires_in' => 3599]);
        }

        if ($request->method() === 'GET' && Str::startsWith($url, BACKUP_TEST_DRIVE_API.'/files')) {
            $matches = array_values(array_filter(
                $drive->folders,
                fn (array $folder): bool => $request['q'] === "name = '{$folder['name']}' and '{$folder['parent']}' in parents and mimeType = '".BACKUP_TEST_FOLDER_MIME."' and trashed = false",
            ));

            return Http::response(['files' => array_map(fn (array $folder): array => ['id' => $folder['id']], $matches)]);
        }

        if ($request->method() === 'POST' && Str::startsWith($url, BACKUP_TEST_DRIVE_API.'/files')) {
            $id = 'folder-'.(count($drive->folders) + 1);
            $drive->folders[] = ['id' => $id, 'name' => $request['name'], 'parent' => data_get($request->data(), 'parents.0'), 'mimeType' => $request['mimeType']];

            return Http::response(['id' => $id]);
        }

        if ($request->method() === 'POST' && Str::startsWith($url, BACKUP_TEST_UPLOAD_API.'/files')) {
            $session = 'session-'.(count($drive->sessions) + 1);
            $drive->sessions[$session] = [
                'metadata' => $request->data(),
                'query' => $url,
                'authorization' => $request->header('Authorization')[0] ?? null,
                'length' => (int) $request->header('X-Upload-Content-Length')[0],
                'type' => $request->header('X-Upload-Content-Type')[0] ?? null,
                'chunks' => [],
                'bytes' => '',
            ];

            return Http::response('', 200, ['Location' => BACKUP_TEST_UPLOAD_API."/files?uploadType=resumable&upload_id={$session}"]);
        }

        if ($request->method() === 'PUT' && Str::startsWith($url, BACKUP_TEST_UPLOAD_API.'/files')) {
            if ($drive->failUploads) {
                return Http::response(['error' => ['code' => 500, 'message' => 'Backend Error']], 500);
            }

            $session = Str::after($url, 'upload_id=');
            $drive->sessions[$session]['chunks'][] = $request->header('Content-Range')[0];
            $drive->sessions[$session]['bytes'] .= $request->body();
            $received = strlen($drive->sessions[$session]['bytes']);

            if ($received < $drive->sessions[$session]['length']) {
                return Http::response('', 308, ['Range' => 'bytes=0-'.($received - 1)]);
            }

            $fileId = "drive-file-{$session}";

            return Http::response(['id' => $fileId, 'webViewLink' => "https://drive.google.test/file/d/{$fileId}/view"]);
        }

        return Http::response('Unexpected request', 418);
    });

    return $drive;
}

test('a group of posts sharing one video is backed up to drive once, then every copy is deleted', function () {
    $drive = fakeDrive();
    ['post' => $first, 'video' => $original] = backupGroupPost('deskchannel');
    ['post' => $second, 'video' => $secondCopy] = backupGroupPost('otherchannel', $original);
    ['post' => $third, 'video' => $thirdCopy] = backupGroupPost('thirdchannel', $original);

    markBackupPostPublished($first);
    $this->travel(2)->minutes();
    markBackupPostPublished($second);
    markBackupPostPublished($third);

    $this->travel(1)->hours();
    runDueBackupJobs();

    $month = $first->refresh()->published_at->setTimezone('America/Sao_Paulo');
    expect($drive->folders)->toBe([
        ['id' => 'folder-1', 'name' => 'The RX Desk', 'parent' => 'root', 'mimeType' => BACKUP_TEST_FOLDER_MIME],
        ['id' => 'folder-2', 'name' => 'Acme Store', 'parent' => 'folder-1', 'mimeType' => BACKUP_TEST_FOLDER_MIME],
        ['id' => 'folder-3', 'name' => $month->format('Y-m'), 'parent' => 'folder-2', 'mimeType' => BACKUP_TEST_FOLDER_MIME],
    ])
        ->and($drive->sessions)->toHaveCount(1)
        ->and($drive->sessions['session-1']['metadata'])->toEqual([
            'name' => "{$month->format('Y-m-d')}_telegram-deskchannel_{$first->id}.mp4",
            'parents' => ['folder-3'],
        ])
        ->and($drive->sessions['session-1']['authorization'])->toBe('Bearer drive-access')
        ->and($drive->sessions['session-1']['type'])->toBe('video/mp4')
        ->and($drive->sessions['session-1']['bytes'])->toBe('video bytes');

    foreach ([$first, $second, $third] as $post) {
        expect($post->refresh()->media)->toBe([])
            ->and($post->status)->toBe(PostStatus::Published);
    }

    expect(Media::query()->whereKey([$original->id, $secondCopy->id, $thirdCopy->id])->count())->toBe(0);
    Storage::assertMissing([$original->path, $secondCopy->path, $thirdCopy->path]);

    $records = VideoRecord::query()->get();
    expect($records)->toHaveCount(3)
        ->and($records->pluck('post_id')->sort()->values()->all())->toBe(collect([$first->id, $second->id, $third->id])->sort()->values()->all());

    foreach ($records as $record) {
        expect($record->outcome)->toBe(Outcome::BackedUp)
            ->and($record->drive_file_id)->toBe('drive-file-session-1')
            ->and($record->drive_link)->toBe('https://drive.google.test/file/d/drive-file-session-1/view')
            ->and($record->original_filename)->toBe('launch.mp4');
    }
});

test('a video is streamed to drive in chunks of the configured size', function () {
    config()->set('trypost.media_sources.google_drive.backup_chunk_bytes', 4);
    $drive = fakeDrive();
    ['post' => $post, 'video' => $video] = backupGroupPost('deskchannel', contents: '0123456789');

    markBackupPostPublished($post);
    $this->travel(1)->hours();
    runDueBackupJobs();

    expect($drive->sessions['session-1']['chunks'])->toBe(['bytes 0-3/10', 'bytes 4-7/10', 'bytes 8-9/10'])
        ->and($drive->sessions['session-1']['bytes'])->toBe('0123456789')
        ->and(VideoRecord::query()->sole()->outcome)->toBe(Outcome::BackedUp);
    Storage::assertMissing($video->path);
});

test('an expired drive token is refreshed with the stored refresh token before the upload', function () {
    $this->connection->forceFill(['expires_at' => now()->subMinute(), 'refresh_token' => 'stored-refresh'])->save();
    $drive = fakeDrive();
    ['post' => $post] = backupGroupPost('deskchannel');

    markBackupPostPublished($post);
    $this->travel(1)->hours();
    runDueBackupJobs();

    Http::assertSent(fn (Request $request): bool => $request->url() === BACKUP_TEST_TOKEN_API.'/token'
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'stored-refresh');
    expect($drive->sessions['session-1']['authorization'])->toBe('Bearer drive-refreshed')
        ->and($this->connection->refresh()->access_token)->toBe('drive-refreshed')
        ->and($this->connection->expires_at->isFuture())->toBeTrue();
});

test('a post whose group upload is not done yet waits, then reuses the drive file', function () {
    $drive = fakeDrive();
    $drive->failUploads = true;
    ['post' => $first, 'video' => $original] = backupGroupPost('deskchannel');
    ['post' => $second, 'video' => $copy] = backupGroupPost('otherchannel', $original);

    markBackupPostPublished($first);
    $this->travel(2)->minutes();
    markBackupPostPublished($second);

    $this->travel(58)->minutes();
    runDueBackupJobs();
    $this->travel(2)->minutes();
    runDueBackupJobs();

    expect($drive->sessions)->toHaveCount(1)
        ->and(VideoRecord::query()->count())->toBe(0)
        ->and(Media::query()->whereKey([$original->id, $copy->id])->count())->toBe(2);
    Storage::assertExists([$original->path, $copy->path]);

    $drive->failUploads = false;
    $this->travel(5)->minutes();
    runDueBackupJobs();
    $this->travel(2)->minutes();
    runDueBackupJobs();

    expect($drive->sessions)->toHaveCount(2)
        ->and($drive->sessions['session-2']['metadata']['name'])->toEndWith("_{$first->id}.mp4")
        ->and(VideoRecord::query()->pluck('drive_file_id')->all())->toBe(['drive-file-session-2', 'drive-file-session-2'])
        ->and(Media::query()->whereKey([$original->id, $copy->id])->count())->toBe(0);
    Storage::assertMissing([$original->path, $copy->path]);
    $this->assertDatabaseCount('failed_jobs', 0);
});

test('a failed upload keeps the video and records no backup', function () {
    $drive = fakeDrive();
    $drive->failUploads = true;
    ['post' => $post, 'video' => $video] = backupGroupPost('deskchannel');

    markBackupPostPublished($post);
    $this->travel(1)->hours();
    runDueBackupJobs();

    foreach (ApplyVideoPolicy::BACKOFF_SECONDS as $seconds) {
        $this->travel($seconds)->seconds();
        runDueBackupJobs();
    }

    expect($drive->sessions)->toHaveCount(6)
        ->and(VideoRecord::query()->count())->toBe(0)
        ->and(data_get($post->refresh()->media, '0.id'))->toBe($video->id);
    Storage::assertExists($video->path);
    expect($this->queue->size('default'))->toBe(0);
});
