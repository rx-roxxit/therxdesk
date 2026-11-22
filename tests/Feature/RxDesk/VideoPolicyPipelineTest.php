<?php

declare(strict_types=1);

use App\Enums\Account\VideoPolicy;
use App\Enums\Media\Source;
use App\Enums\Post\PublishStatus;
use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Enums\VideoRecord\Outcome;
use App\Jobs\Media\DeleteMediaFiles;
use App\Jobs\PublishPost;
use App\Jobs\PublishToSocialPlatform;
use App\Jobs\VideoPolicy\ApplyVideoPolicy;
use App\Models\Media;
use App\Models\MediaSourceConnection;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\VideoRecord;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->freezeTime();
    config(['filesystems.default' => 'r2']);
    Storage::fake('r2', ['url' => 'https://media.therxdesk.test']);
    config()->set('queue.default', 'database');

    Queue::swap(Queue::getFacadeRoot()->queue);
    $this->queue = Queue::connection('database');
    $this->worker = app('queue.worker');
    Bus::fake()->except([
        PublishPost::class,
        PublishToSocialPlatform::class,
        ApplyVideoPolicy::class,
        DeleteMediaFiles::class,
    ]);
    $this->workerOptions = new WorkerOptions(sleep: 0, maxTries: 1);
});

/**
 * A Telegram post due now, carrying a video and an image, whose account
 * follows `$policy`.
 *
 * @return array{post: Post, video: Media, image: Media}
 */
function videoPolicyPost(VideoPolicy $policy): array
{
    $account = SocialAccount::factory()->telegram()->create();
    $account->workspace->account->forceFill(['video_policy' => $policy])->save();

    $post = Post::factory()->forAccount($account)->scheduled()->create([
        'user_id' => $account->workspace->user_id,
        'scheduled_at' => now()->subMinute(),
        'content' => 'Launch day',
    ]);
    $video = Media::factory()->ownedByPost($post)->stored()->video()->create([
        'size' => 2_000_000,
        'original_filename' => 'launch.mp4',
        'meta' => ['width' => 1080, 'height' => 1920, 'duration' => 15],
        'order' => 0,
    ]);
    $image = Media::factory()->ownedByPost($post)->stored()->create(['size' => 100_000, 'order' => 1]);
    $post->update(['media' => [$video->toArray(), $image->toArray()]]);

    return ['post' => $post->fresh(), 'video' => $video, 'image' => $image];
}

function fakeTelegram(int $status = 200): void
{
    $body = $status === 200
        ? ['ok' => true, 'result' => [['message_id' => 42]]]
        : ['ok' => false, 'error_code' => $status, 'description' => 'Bad Request: wrong file'];

    Http::fake([config('trypost.platforms.telegram.api').'/*' => Http::response($body, $status)]);
}

/**
 * Runs the scheduler, then the publish jobs, the way the workers would.
 */
function publishThroughPipeline(): void
{
    test()->artisan('posts:process-scheduled')->assertSuccessful();
    test()->worker->runNextJob('database', 'default', test()->workerOptions);
    test()->worker->runNextJob('database', Platform::Telegram->queue(), test()->workerOptions);
}

/**
 * Runs every job that is due: the policy job on its own queue, then the file
 * deletion it queues on the default one.
 */
function runDueDefaultJobs(): void
{
    while (test()->queue->pendingSize(ApplyVideoPolicy::QUEUE) + test()->queue->pendingSize('default') > 0) {
        test()->worker->runNextJob('database', ApplyVideoPolicy::QUEUE.',default', test()->workerOptions);
    }

    test()->assertDatabaseCount('failed_jobs', 0);
}

/**
 * @return list<string|null>
 */
function postMediaIds(Post $post): array
{
    return array_map(fn (array $item): ?string => data_get($item, 'id'), $post->refresh()->media ?? []);
}

test('keep leaves the video one hour after publishing', function () {
    ['post' => $post, 'video' => $video, 'image' => $image] = videoPolicyPost(VideoPolicy::Keep);
    fakeTelegram();

    publishThroughPipeline();
    expect($post->refresh()->status)->toBe(PostStatus::Published);

    $this->travel(1)->hours();
    runDueDefaultJobs();

    expect(Media::query()->whereKey([$video->id, $image->id])->count())->toBe(2)
        ->and(postMediaIds($post))->toBe([$video->id, $image->id])
        ->and(VideoRecord::query()->count())->toBe(0);
    Storage::assertExists([$video->path, $image->path]);
});

test('delete removes only the video one hour after publishing and records it', function () {
    ['post' => $post, 'video' => $video, 'image' => $image] = videoPolicyPost(VideoPolicy::Delete);
    fakeTelegram();

    publishThroughPipeline();
    expect($post->refresh()->status)->toBe(PostStatus::Published);

    $this->travel(59)->minutes();
    runDueDefaultJobs();
    expect(Media::query()->whereKey($video->id)->exists())->toBeTrue();
    Storage::assertExists($video->path);

    $this->travel(1)->minutes();
    runDueDefaultJobs();

    expect(Media::query()->whereKey($video->id)->exists())->toBeFalse()
        ->and(Media::query()->whereKey($image->id)->exists())->toBeTrue()
        ->and(postMediaIds($post))->toBe([$image->id])
        ->and($post->status)->toBe(PostStatus::Published);
    Storage::assertMissing($video->path);
    Storage::assertExists($image->path);

    $record = VideoRecord::query()->sole();
    expect($record->post_id)->toBe($post->id)
        ->and($record->original_filename)->toBe('launch.mp4')
        ->and($record->size)->toBe(2_000_000)
        ->and($record->outcome)->toBe(Outcome::Deleted);
});

test('a switch to keep before the hour is up keeps the video', function () {
    ['post' => $post, 'video' => $video] = videoPolicyPost(VideoPolicy::Delete);
    fakeTelegram();

    publishThroughPipeline();
    $post->workspace->account->forceFill(['video_policy' => VideoPolicy::Keep])->save();

    $this->travel(1)->hours();
    runDueDefaultJobs();

    expect(postMediaIds($post))->toContain($video->id)
        ->and(VideoRecord::query()->count())->toBe(0);
    Storage::assertExists($video->path);
});

test('a switch to delete before the hour is up deletes the video', function () {
    ['post' => $post, 'video' => $video] = videoPolicyPost(VideoPolicy::Keep);
    fakeTelegram();

    publishThroughPipeline();
    $post->workspace->account->forceFill(['video_policy' => VideoPolicy::Delete])->save();

    $this->travel(1)->hours();
    runDueDefaultJobs();

    expect(postMediaIds($post))->not->toContain($video->id);
    Storage::assertMissing($video->path);
});

test('a post that failed to publish keeps its video', function () {
    ['post' => $post, 'video' => $video] = videoPolicyPost(VideoPolicy::Delete);
    fakeTelegram(400);

    publishThroughPipeline();
    expect($post->refresh()->status)->toBe(PostStatus::Failed);

    $this->travel(1)->hours();
    runDueDefaultJobs();

    expect(postMediaIds($post))->toContain($video->id)
        ->and(VideoRecord::query()->count())->toBe(0);
    Storage::assertExists($video->path);
});

test('a post no longer published when the hour is up keeps its video', function (PostStatus $status, PublishStatus $publishStatus) {
    ['post' => $post, 'video' => $video] = videoPolicyPost(VideoPolicy::Delete);
    fakeTelegram();

    publishThroughPipeline();
    expect($post->refresh()->status)->toBe(PostStatus::Published);
    $post->forceFill(['status' => $status, 'publish_status' => $publishStatus])->save();

    $this->travel(1)->hours();
    runDueDefaultJobs();

    expect(postMediaIds($post))->toContain($video->id)
        ->and(VideoRecord::query()->count())->toBe(0);
    Storage::assertExists($video->path);
})->with([
    'failed' => [PostStatus::Failed, PublishStatus::Failed],
    'retrying' => [PostStatus::Publishing, PublishStatus::Retrying],
    'pending' => [PostStatus::Publishing, PublishStatus::Pending],
    'pending review' => [PostStatus::Publishing, PublishStatus::PendingReview],
]);

test('only a move into published starts the clock', function () {
    ['post' => $post, 'video' => $video] = videoPolicyPost(VideoPolicy::Delete);

    $post->forceFill(['status' => PostStatus::Publishing, 'publish_status' => PublishStatus::Retrying])->save();
    $post->forceFill(['status' => PostStatus::Failed, 'publish_status' => PublishStatus::Failed])->save();

    expect($this->queue->size(ApplyVideoPolicy::QUEUE))->toBe(0);

    $this->travel(1)->hours();
    runDueDefaultJobs();

    Storage::assertExists($video->path);
});

test('delete also removes a video from a thread reply', function () {
    $account = SocialAccount::factory()->create(['platform' => Platform::Bluesky]);
    $account->workspace->account->forceFill(['video_policy' => VideoPolicy::Delete])->save();
    $post = Post::factory()->forAccount($account)->create([
        'status' => PostStatus::Published,
        'publish_status' => PublishStatus::Published,
        'published_at' => now(),
    ]);
    $image = Media::factory()->ownedByPost($post)->stored()->create();
    $replyVideo = Media::factory()->ownedByPost($post)->stored()->video()->create(['order' => 1]);
    $post->update([
        'media' => [$image->toArray()],
        'meta' => ['thread_replies' => [['text' => 'More', 'media' => [$replyVideo->toArray()]]]],
    ]);

    ApplyVideoPolicy::dispatch($post->id);
    runDueDefaultJobs();

    $post->refresh();
    expect(postMediaIds($post))->toBe([$image->id])
        ->and(data_get($post->meta, 'thread_replies.0.text'))->toBe('More')
        ->and(data_get($post->meta, 'thread_replies.0.media'))->toBe([])
        ->and(VideoRecord::query()->sole()->media_id)->toBe($replyVideo->id);
    Storage::assertMissing($replyVideo->path);
    Storage::assertExists($image->path);
});

test('backup uploads the video to drive one hour after publishing, then deletes it from the server', function () {
    config()->set([
        'trypost.media_sources.google_drive.api' => 'https://drive.google.test/drive/v3',
        'trypost.media_sources.google_drive.upload_api' => 'https://drive.google.test/upload/drive/v3',
    ]);
    ['post' => $post, 'video' => $video, 'image' => $image] = videoPolicyPost(VideoPolicy::Backup);
    MediaSourceConnection::factory()->create([
        'user_id' => $post->workspace->account->owner_id,
        'source' => Source::GoogleDriveBackup,
        'access_token' => 'drive-access',
    ]);
    Http::fake(function (Request $request) {
        if (Str::startsWith($request->url(), config('trypost.platforms.telegram.api'))) {
            return Http::response(['ok' => true, 'result' => [['message_id' => 42]]]);
        }

        if (Str::startsWith($request->url(), 'https://drive.google.test/drive/v3/files')) {
            return Http::response($request->method() === 'GET' ? ['files' => []] : ['id' => 'folder-id']);
        }

        if ($request->method() === 'POST') {
            return Http::response('', 200, ['Location' => 'https://drive.google.test/upload/drive/v3/files?uploadType=resumable&upload_id=session']);
        }

        return Http::response(['id' => 'drive-file', 'webViewLink' => 'https://drive.google.test/file/d/drive-file/view']);
    });

    publishThroughPipeline();
    expect($post->refresh()->status)->toBe(PostStatus::Published);

    $this->travel(59)->minutes();
    runDueDefaultJobs();
    expect(Media::query()->whereKey($video->id)->exists())->toBeTrue();
    Http::assertNotSent(fn (Request $request): bool => Str::contains($request->url(), 'upload_id=session'));

    $this->travel(1)->minutes();
    runDueDefaultJobs();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && Str::contains($request->url(), 'upload_id=session'));
    expect(Media::query()->whereKey($video->id)->exists())->toBeFalse()
        ->and(postMediaIds($post))->toBe([$image->id])
        ->and($post->status)->toBe(PostStatus::Published);
    Storage::assertMissing($video->path);
    Storage::assertExists($image->path);

    $record = VideoRecord::query()->sole();
    expect($record->outcome)->toBe(Outcome::BackedUp)
        ->and($record->drive_file_id)->toBe('drive-file')
        ->and($record->drive_link)->toBe('https://drive.google.test/file/d/drive-file/view');
});

test('delete removes the Cover image of the video with it', function () {
    ['post' => $post, 'video' => $video, 'image' => $image] = videoPolicyPost(VideoPolicy::Delete);
    $cover = Media::factory()->ownedByPost($post)->stored()->create(['order' => 2]);
    $post->update(['media' => [
        [...$video->toArray(), 'meta' => [...$video->meta, 'cover_image' => $cover->toArray()]],
        $image->toArray(),
    ]]);
    fakeTelegram();

    publishThroughPipeline();
    $this->travel(1)->hours();
    runDueDefaultJobs();

    expect(postMediaIds($post))->toBe([$image->id])
        ->and(Media::query()->whereKey($cover->id)->exists())->toBeFalse()
        ->and(VideoRecord::query()->sole()->media_id)->toBe($video->id);
    Storage::assertMissing([$video->path, $cover->path]);
    Storage::assertExists($image->path);
});
