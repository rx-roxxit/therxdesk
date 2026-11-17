<?php

declare(strict_types=1);

use App\Actions\VideoPolicy\BackupPostVideos;
use App\Enums\Account\VideoPolicy;
use App\Enums\Media\Source;
use App\Enums\Post\PublishStatus;
use App\Enums\Post\Status as PostStatus;
use App\Enums\VideoRecord\Outcome;
use App\Jobs\Media\DeleteMediaFiles;
use App\Jobs\VideoPolicy\ApplyVideoPolicy;
use App\Mail\VideoBackupFailed;
use App\Models\Media;
use App\Models\MediaSourceConnection;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\VideoRecord;
use App\Models\Workspace;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

const BACKUP_TEST_DRIVE_API = 'https://drive.google.test/drive/v3';

const BACKUP_TEST_UPLOAD_API = 'https://drive.google.test/upload/drive/v3';

const BACKUP_TEST_TOKEN_API = 'https://oauth.google.test';

const BACKUP_TEST_FOLDER_MIME = 'application/vnd.google-apps.folder';

beforeEach(function () {
    $this->freezeTime();
    Storage::fake();
    Mail::fake();
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
 * Runs every job that is due: the policy jobs on their own queue, then the
 * file deletions they queue on the default one.
 */
function runDueBackupJobs(): void
{
    while (test()->queue->pendingSize(ApplyVideoPolicy::QUEUE) + test()->queue->pendingSize('default') > 0) {
        test()->worker->runNextJob('database', ApplyVideoPolicy::QUEUE.',default', test()->workerOptions);
    }
}

/**
 * How many policy jobs are queued, due or not.
 */
function queuedPolicyJobs(): int
{
    return test()->queue->size(ApplyVideoPolicy::QUEUE);
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
    $drive->tokenGrantRejected = false;
    $drive->onChunk = null;

    Http::fake(function (Request $request) use ($drive) {
        $url = $request->url();

        if (Str::startsWith($url, BACKUP_TEST_TOKEN_API)) {
            if ($drive->tokenGrantRejected) {
                return Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400);
            }

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

            if ($drive->onChunk !== null) {
                ($drive->onChunk)();
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

test('every post of a group tries the upload while it fails, and the group still ends with one drive copy', function () {
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

    expect($drive->sessions)->toHaveCount(2)
        ->and($drive->sessions['session-1']['metadata']['name'])->toEndWith("_{$first->id}.mp4")
        ->and($drive->sessions['session-2']['metadata']['name'])->toEndWith("_{$second->id}.mp4")
        ->and(VideoRecord::query()->count())->toBe(0)
        ->and(Media::query()->whereKey([$original->id, $copy->id])->count())->toBe(2);
    Storage::assertExists([$original->path, $copy->path]);

    $drive->failUploads = false;
    $this->travel(5)->minutes();
    runDueBackupJobs();

    expect($drive->sessions)->toHaveCount(3)
        ->and(VideoRecord::query()->pluck('drive_file_id')->all())->toBe(['drive-file-session-3', 'drive-file-session-3'])
        ->and(Media::query()->whereKey([$original->id, $copy->id])->count())->toBe(0)
        ->and(queuedPolicyJobs())->toBe(0);
    Storage::assertMissing([$original->path, $copy->path]);
    $this->assertDatabaseCount('failed_jobs', 0);
});

test('a group whose first post published under keep is backed up by the next one, and the first keeps its file', function () {
    $drive = fakeDrive();
    $this->account->forceFill(['video_policy' => VideoPolicy::Keep])->save();
    ['post' => $first, 'video' => $original] = backupGroupPost('deskchannel');
    ['post' => $second, 'video' => $copy] = backupGroupPost('otherchannel', $original);

    markBackupPostPublished($first);
    $this->travel(1)->hours();
    runDueBackupJobs();

    $this->account->forceFill(['video_policy' => VideoPolicy::Backup])->save();
    markBackupPostPublished($second);
    $this->travel(1)->hours();
    runDueBackupJobs();

    expect($drive->sessions)->toHaveCount(1)
        ->and($drive->sessions['session-1']['metadata']['name'])->toEndWith("_{$second->id}.mp4")
        ->and(VideoRecord::query()->sole()->post_id)->toBe($second->id)
        ->and(Media::query()->whereKey($copy->id)->exists())->toBeFalse()
        ->and(data_get($first->refresh()->media, '0.id'))->toBe($original->id)
        ->and(queuedPolicyJobs())->toBe(0);
    Storage::assertExists($original->path);
    Storage::assertMissing($copy->path);
    $this->assertDatabaseCount('failed_jobs', 0);
});

test('a backup that keeps failing is recorded as failed after the last try, keeps the file and emails the Owner once', function () {
    $drive = fakeDrive();
    $drive->failUploads = true;
    ['post' => $post, 'video' => $video] = backupGroupPost('deskchannel');

    markBackupPostPublished($post);
    $this->travel(1)->hours();
    runDueBackupJobs();

    foreach (ApplyVideoPolicy::BACKOFF_SECONDS as $index => $seconds) {
        expect(VideoRecord::query()->count())->toBe(0);
        Mail::assertNothingQueued();

        $this->travel($seconds)->seconds();
        runDueBackupJobs();
    }

    $record = VideoRecord::query()->sole();

    expect($drive->sessions)->toHaveCount(6)
        ->and($record->outcome)->toBe(Outcome::BackupFailed)
        ->and($record->post_id)->toBe($post->id)
        ->and($record->media_id)->toBe($video->id)
        ->and($record->attempts)->toBe(6)
        ->and($record->last_error)->toContain('500')
        ->and(data_get($post->refresh()->media, '0.id'))->toBe($video->id);
    Storage::assertExists($video->path);
    expect(Media::query()->whereKey($video->id)->exists())->toBeTrue()
        ->and(queuedPolicyJobs())->toBe(0);
    $this->assertDatabaseCount('failed_jobs', 0);

    Mail::assertQueuedCount(1);
    Mail::assertQueued(VideoBackupFailed::class, fn (VideoBackupFailed $mail): bool => $mail->hasTo($this->account->owner->email)
        && $mail->post->is($post)
        && $mail->needsReconnect === false);
});

test('a token Google rejects marks the connection as needing reconnect and fails the backup at once', function () {
    $this->connection->forceFill(['expires_at' => now()->subMinute(), 'refresh_token' => 'stored-refresh'])->save();
    $drive = fakeDrive();
    $drive->tokenGrantRejected = true;
    ['post' => $post, 'video' => $video] = backupGroupPost('deskchannel');

    markBackupPostPublished($post);
    $this->travel(1)->hours();
    runDueBackupJobs();

    expect($this->connection->refresh()->needsReconnect())->toBeTrue()
        ->and($drive->sessions)->toHaveCount(0)
        ->and(queuedPolicyJobs())->toBe(0);

    $record = VideoRecord::query()->sole();
    expect($record->outcome)->toBe(Outcome::BackupFailed)
        ->and($record->attempts)->toBe(1)
        ->and(Media::query()->whereKey($video->id)->exists())->toBeTrue();
    Storage::assertExists($video->path);

    Mail::assertQueued(VideoBackupFailed::class, fn (VideoBackupFailed $mail): bool => $mail->needsReconnect === true);

    $this->actingAs($this->account->owner)
        ->get(route('app.settings.preferences'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('driveConnection', 'needs_reconnect'));
});

test('a second run of the same upload does not upload the file twice at once', function () {
    $drive = fakeDrive();
    ['post' => $post] = backupGroupPost('deskchannel');
    markBackupPostPublished($post);
    $second = null;
    $drive->onChunk = function () use ($drive, $post, &$second): void {
        $drive->onChunk = null;
        $second = BackupPostVideos::execute($post->fresh());
    };

    $this->travel(1)->hours();
    runDueBackupJobs();

    expect($second)->toBeFalse()
        ->and($drive->sessions)->toHaveCount(1)
        ->and(VideoRecord::query()->sole()->outcome)->toBe(Outcome::BackedUp);
});

test('a file whose upload lock is held is not uploaded until the lock is released', function () {
    $drive = fakeDrive();
    ['post' => $post, 'video' => $video] = backupGroupPost('deskchannel');
    markBackupPostPublished($post);
    $lock = Cache::lock("video-backup:{$this->groupId}:{$video->id}", 86400);
    $lock->get();

    $this->travel(1)->hours();
    runDueBackupJobs();

    expect($drive->sessions)->toHaveCount(0)
        ->and(VideoRecord::query()->count())->toBe(0);

    $lock->release();
    $this->travel(5)->minutes();
    runDueBackupJobs();

    expect($drive->sessions)->toHaveCount(1)
        ->and(VideoRecord::query()->sole()->outcome)->toBe(Outcome::BackedUp);
});

test('a post waiting on another run\'s long upload does not use up its tries', function () {
    $drive = fakeDrive();
    ['post' => $post, 'video' => $video] = backupGroupPost('deskchannel');
    markBackupPostPublished($post);
    $lock = Cache::lock("video-backup:{$this->groupId}:{$video->id}", 86400);
    $lock->get();

    $this->travel(1)->hours();
    runDueBackupJobs();

    foreach (range(1, 24) as $poll) {
        $this->travel(ApplyVideoPolicy::WAIT_SECONDS)->seconds();
        runDueBackupJobs();
    }

    expect($drive->sessions)->toHaveCount(0)
        ->and(VideoRecord::query()->count())->toBe(0)
        ->and(queuedPolicyJobs())->toBe(1);
    Mail::assertNothingQueued();
    Storage::assertExists($video->path);

    $lock->release();
    $this->travel(ApplyVideoPolicy::WAIT_SECONDS)->seconds();
    runDueBackupJobs();

    expect($drive->sessions)->toHaveCount(1)
        ->and(VideoRecord::query()->sole()->outcome)->toBe(Outcome::BackedUp)
        ->and(queuedPolicyJobs())->toBe(0);
    Storage::assertMissing($video->path);
});

test('every post of a group whose upload keeps failing is marked failed, and the Owner gets one email', function () {
    $drive = fakeDrive();
    $drive->failUploads = true;
    ['post' => $first, 'video' => $original] = backupGroupPost('deskchannel');
    ['post' => $second, 'video' => $copy] = backupGroupPost('otherchannel', $original);
    markBackupPostPublished($first);
    $this->travel(2)->minutes();
    markBackupPostPublished($second);

    $this->travel(1)->hours();
    runDueBackupJobs();

    foreach (ApplyVideoPolicy::BACKOFF_SECONDS as $seconds) {
        $this->travel($seconds)->seconds();
        runDueBackupJobs();
    }

    $records = VideoRecord::query()->get()->keyBy('post_id');
    expect($records)->toHaveCount(2)
        ->and($records[$first->id]->outcome)->toBe(Outcome::BackupFailed)
        ->and($records[$second->id]->outcome)->toBe(Outcome::BackupFailed)
        ->and(queuedPolicyJobs())->toBe(0);
    Storage::assertExists([$original->path, $copy->path]);
    Mail::assertQueuedCount(1);
});

test('the backup runs on its own queue, whose worker and redis connection outlast a large upload', function () {
    config()->set('queue.default', 'redis');
    $job = new ApplyVideoPolicy('post-id');
    $supervisor = config('horizon.defaults.video-policy');
    $others = collect(config('horizon.defaults'))
        ->except('video-policy')
        ->flatMap(fn (array $other): array => (array) data_get($other, 'queue', []))
        ->all();

    expect($job->queue)->toBe(ApplyVideoPolicy::QUEUE)
        ->and($job->connection)->toBe('video-policy')
        ->and(config('queue.connections.video-policy.driver'))->toBe('redis')
        ->and(data_get($supervisor, 'connection'))->toBe('video-policy')
        ->and(data_get($supervisor, 'queue'))->toBe([ApplyVideoPolicy::QUEUE])
        ->and($others)->not->toContain(ApplyVideoPolicy::QUEUE)
        ->and(config('horizon.environments.production.video-policy'))->toBeArray()
        ->and(config('horizon.environments.local.video-policy'))->toBeArray()
        ->and($job->timeout)->toBeGreaterThanOrEqual(2 * 3600)
        ->and($job->timeout)->toBeLessThan(data_get($supervisor, 'timeout'))
        ->and(data_get($supervisor, 'timeout'))->toBeLessThan(config('queue.connections.video-policy.retry_after'))
        ->and(BackupPostVideos::UPLOAD_LOCK_SECONDS)->toBeGreaterThan($job->timeout);
});

test('a backup job queued without redis stays on the default connection', function () {
    config()->set('queue.default', 'database');

    expect((new ApplyVideoPolicy('post-id'))->connection)->toBeNull();
});

test('an upload that throws releases its lock at once', function () {
    $drive = fakeDrive();
    $drive->failUploads = true;
    ['post' => $post, 'video' => $video] = backupGroupPost('deskchannel');
    markBackupPostPublished($post);

    expect(fn () => BackupPostVideos::execute($post->fresh()))->toThrow(Exception::class);

    $lock = Cache::lock("video-backup:{$this->groupId}:{$video->id}", 10);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

test('retry runs the backup again right away and uploads when the policy is still backup', function () {
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
    expect(VideoRecord::query()->sole()->outcome)->toBe(Outcome::BackupFailed);

    $drive->failUploads = false;
    $this->actingAs(retryOwner())
        ->post(route('app.posts.video-backup.retry', $post))
        ->assertRedirect();
    runDueBackupJobs();

    $record = VideoRecord::query()->sole();
    expect($record->outcome)->toBe(Outcome::BackedUp)
        ->and(Media::query()->whereKey($video->id)->exists())->toBeFalse();
    Storage::assertMissing($video->path);
});

test('retry applies the policy in force at that moment', function (VideoPolicy $policy, ?Outcome $outcome, bool $fileKept) {
    $drive = fakeDrive();
    ['post' => $post, 'video' => $video] = backupGroupPost('deskchannel');
    markBackupPostPublished($post);
    VideoRecord::factory()->create([
        'post_id' => $post->id,
        'media_id' => $video->id,
        'outcome' => Outcome::BackupFailed,
        'attempts' => 6,
        'last_error' => 'Backend Error',
    ]);
    $this->account->forceFill(['video_policy' => $policy])->save();

    $this->actingAs(retryOwner())->post(route('app.posts.video-backup.retry', $post))->assertRedirect();
    runDueBackupJobs();

    expect(VideoRecord::query()->pluck('outcome')->all())->toBe($outcome === null ? [] : [$outcome])
        ->and(Media::query()->whereKey($video->id)->exists())->toBe($fileKept);
    expect($drive->sessions)->toHaveCount($policy === VideoPolicy::Backup ? 1 : 0);
})->with([
    'keep stops and clears the failure' => [VideoPolicy::Keep, null, true],
    'delete deletes' => [VideoPolicy::Delete, Outcome::Deleted, false],
    'backup uploads' => [VideoPolicy::Backup, Outcome::BackedUp, false],
]);

test('only the Owner can retry a backup', function () {
    fakeDrive();
    ['post' => $post] = backupGroupPost('deskchannel');
    markBackupPostPublished($post);
    $record = VideoRecord::factory()->create(['post_id' => $post->id, 'outcome' => Outcome::BackupFailed]);

    $member = workspaceMember($this->workspace, 'admin');
    $queued = queuedPolicyJobs();

    $this->actingAs($member)->post(route('app.posts.video-backup.retry', $post))->assertForbidden();
    expect(queuedPolicyJobs())->toBe($queued)
        ->and($record->fresh())->not->toBeNull();

    $this->actingAs(retryOwner())->post(route('app.posts.video-backup.retry', $post))->assertRedirect();
    expect($record->fresh())->toBeNull();
});

function retryOwner(): User
{
    $owner = test()->account->owner;
    $owner->update(['current_workspace_id' => test()->workspace->id]);

    return $owner->fresh();
}

test('retry is refused for a post whose backup has not failed', function () {
    $drive = fakeDrive();
    ['post' => $post, 'video' => $video] = backupGroupPost('deskchannel');
    markBackupPostPublished($post);
    $queued = queuedPolicyJobs();

    $this->actingAs(retryOwner())
        ->postJson(route('app.posts.video-backup.retry', $post))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['post' => "This post has no failed backup to retry."]);

    expect(queuedPolicyJobs())->toBe($queued);
    runDueBackupJobs();

    expect($drive->sessions)->toHaveCount(0)
        ->and(Media::query()->whereKey($video->id)->exists())->toBeTrue();
});

test('a video with a Cover image is backed up alone, and its Cover image is deleted with it', function () {
    $drive = fakeDrive();
    ['post' => $post, 'video' => $video] = backupGroupPost('deskchannel');
    $cover = Media::factory()->ownedByPost($post)->create(['order' => 1]);
    Storage::put($cover->path, 'cover bytes');
    $post->update(['media' => [[...$video->toArray(), 'meta' => [...$video->meta, 'cover_image' => $cover->toArray()]]]]);

    markBackupPostPublished($post);
    $this->travel(1)->hours();
    runDueBackupJobs();

    expect($drive->sessions)->toHaveCount(1)
        ->and($drive->sessions['session-1']['bytes'])->toBe('video bytes')
        ->and($post->refresh()->media)->toBe([])
        ->and(Media::query()->whereKey([$video->id, $cover->id])->count())->toBe(0)
        ->and(VideoRecord::query()->sole()->media_id)->toBe($video->id);
    Storage::assertMissing([$video->path, $cover->path]);
});
