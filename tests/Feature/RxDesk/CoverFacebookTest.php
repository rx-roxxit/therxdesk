<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Cover\Outcome;
use App\Enums\Post\PublishStatus;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Jobs\Cover\SetPostCover;
use App\Jobs\PublishPost;
use App\Jobs\PublishToSocialPlatform;
use App\Models\CoverOutcome;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->freezeTime();
    Storage::fake();
    Mail::fake();
    config()->set('queue.default', 'database');

    Queue::swap(Queue::getFacadeRoot()->queue);
    $this->queue = Queue::connection('database');
    $this->worker = app('queue.worker');
    Bus::fake()->except([PublishPost::class, PublishToSocialPlatform::class, SetPostCover::class]);
    $this->workerOptions = new WorkerOptions(sleep: 0, maxTries: 1);

    $this->channel = SocialAccount::factory()->facebook()->create([
        'platform_user_id' => 'page_cover',
        'token_expires_at' => now()->addDays(7),
    ]);
    $this->graph = config('trypost.platforms.facebook.graph_api');
});

/**
 * A Facebook post due now, with a Cover image on its video when `$withCover`.
 *
 * @return array{post: Post, cover: Media|null}
 */
function facebookVideoDueNow(SocialAccount $channel, ContentType $type, bool $withCover = true): array
{
    $post = Post::factory()->forAccount($channel, $type)->scheduled()->create([
        'user_id' => $channel->workspace->user_id,
        'scheduled_at' => now()->subMinute(),
        'content' => 'Cover video',
    ]);
    $video = Media::factory()->ownedByPost($post)->video()->create([
        'size' => 4096,
        'meta' => ['width' => 1080, 'height' => 1920, 'duration' => 15],
        'order' => 0,
    ]);
    Storage::put($video->path, str_repeat('v', 4096));
    $item = MediaItem::fromMedia($video)->toArray();
    $cover = null;

    if ($withCover) {
        $cover = Media::factory()->ownedByPost($post)->create([
            'path' => 'media/'.Str::uuid().'.png',
            'mime_type' => 'image/png',
            'original_filename' => 'cover.png',
            'order' => 1,
        ]);
        Storage::put($cover->path, 'png bytes');
        $item['meta'] = [...$item['meta'], 'cover_image' => MediaItem::fromMedia($cover)->toArray()];
    }

    $post->update(['media' => [$item]]);

    return ['post' => $post->fresh(), 'cover' => $cover];
}

/**
 * Fakes the publish and the Cover calls. `$videoStatus` lists what each read of
 * the video's `status` answers once the Cover job runs (a Reel's publish reads
 * it too, and always sees the upload complete), in order, the last one
 * repeating; `$thumbnailStatus` what `/thumbnails` answers, in order.
 *
 * @param  list<string>  $videoStatus
 * @param  int|list<int>  $thumbnailStatus
 */
function fakeFacebook(array $videoStatus = ['ready'], int|array $thumbnailStatus = 200): void
{
    $reads = 0;
    $thumbnails = 0;
    $statuses = (array) $thumbnailStatus;
    $graph = config('trypost.platforms.facebook.graph_api');
    $rupload = 'https://'.config('trypost.platforms.facebook.rupload_host');
    $publishedBy = now()->addSeconds(SetPostCover::FIRST_DELAY_SECONDS);

    Http::fake(function (Request $request) use ($videoStatus, $statuses, $graph, $rupload, $publishedBy, &$reads, &$thumbnails) {
        $url = $request->url();

        if (str_starts_with($url, "{$graph}/page_cover/videos")) {
            return Http::response(['id' => 'video-1']);
        }

        if (str_starts_with($url, "{$graph}/page_cover/video_reels")) {
            return ($request['upload_phase'] ?? null) === 'start'
                ? Http::response(['video_id' => 'video-1', 'upload_url' => "{$rupload}/video-upload/v25.0/video-1"])
                : Http::response(['success' => true, 'post_id' => 'story-1']);
        }

        if (str_starts_with($url, $rupload)) {
            return Http::response(['success' => true]);
        }

        if (str_starts_with($url, "{$graph}/video-1/thumbnails")) {
            $status = $statuses[min($thumbnails++, count($statuses) - 1)];

            return $status === 200
                ? Http::response(['success' => true])
                : Http::response(['error' => ['code' => match ($status) {
                    400 => 100,
                    403 => 200,
                    default => 2,
                }, 'message' => 'refused']], $status);
        }

        if (str_starts_with($url, "{$graph}/video-1") && now()->lt($publishedBy)) {
            return Http::response(['status' => ['video_status' => 'processing', 'uploading_phase' => ['status' => 'complete']]]);
        }

        if (str_starts_with($url, "{$graph}/video-1")) {
            $status = $videoStatus[min($reads++, count($videoStatus) - 1)];

            return Http::response(['status' => ['video_status' => $status, 'uploading_phase' => ['status' => 'complete']]]);
        }

        return Http::response(str_repeat('v', 4096));
    });
}

/**
 * Runs the scheduler and the publish jobs the way the workers would.
 */
function publishFacebookThroughPipeline(): void
{
    test()->artisan('posts:process-scheduled')->assertSuccessful();
    test()->worker->runNextJob('database', 'default', test()->workerOptions);
    test()->worker->runNextJob('database', Platform::Facebook->queue(), test()->workerOptions);
}

/**
 * Runs every Cover job that is due.
 */
function runDueFacebookCoverJobs(): void
{
    while (test()->queue->pendingSize('default') > 0) {
        test()->worker->runNextJob('database', 'default', test()->workerOptions);
    }

    test()->assertDatabaseCount('failed_jobs', 0);
}

/**
 * @return list<Request>
 */
function facebookThumbnailRequests(): array
{
    return Http::recorded(fn (Request $request): bool => str_contains($request->url(), '/video-1/thumbnails'))
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

test('a published Facebook video or Reel sends its Cover once Facebook says the video is ready', function (ContentType $type) {
    ['post' => $post] = facebookVideoDueNow($this->channel, $type);
    fakeFacebook(['processing', 'ready']);

    publishFacebookThroughPipeline();
    expect($post->refresh()->status)->toBe(PostStatus::Published)
        ->and($post->publish_status)->toBe(PublishStatus::Published)
        ->and($post->platform_post_id)->toBe('video-1');
    expect(facebookThumbnailRequests())->toBeEmpty();

    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueFacebookCoverJobs();
    expect(facebookThumbnailRequests())->toBeEmpty()
        ->and(CoverOutcome::query()->count())->toBe(0);

    $this->travel(SetPostCover::BACKOFF_SECONDS[0])->seconds();
    runDueFacebookCoverJobs();

    $sent = facebookThumbnailRequests();

    expect($sent)->toHaveCount(1)
        ->and($sent[0]->method())->toBe('POST')
        ->and($sent[0]->url())->toBe("{$this->graph}/video-1/thumbnails")
        ->and($sent[0]->isMultipart())->toBeTrue()
        ->and(collect($sent[0]->data())->firstWhere('name', 'source')['contents'])->toBe('png bytes')
        ->and(collect($sent[0]->data())->firstWhere('name', 'is_preferred')['contents'])->toBe('true')
        ->and(collect($sent[0]->data())->firstWhere('name', 'access_token')['contents'])->toBe($this->channel->access_token)
        ->and(CoverOutcome::query()->sole()->outcome)->toBe(Outcome::Set);
})->with([
    'a video post' => [ContentType::FacebookPost],
    'a Reel' => [ContentType::FacebookReel],
]);

test('while Facebook keeps processing the video the job tries for about 30 minutes, then records the failure and the post stays published', function () {
    ['post' => $post] = facebookVideoDueNow($this->channel, ContentType::FacebookPost);
    fakeFacebook(['processing']);

    publishFacebookThroughPipeline();

    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueFacebookCoverJobs();

    foreach (SetPostCover::BACKOFF_SECONDS as $seconds) {
        expect(CoverOutcome::query()->count())->toBe(0);

        $this->travel($seconds)->seconds();
        runDueFacebookCoverJobs();
    }

    $outcome = CoverOutcome::query()->sole();

    expect($outcome->outcome)->toBe(Outcome::Failed)
        ->and($outcome->post_id)->toBe($post->id)
        ->and($outcome->attempts)->toBe(8)
        ->and(facebookThumbnailRequests())->toBeEmpty()
        ->and($post->refresh()->status)->toBe(PostStatus::Published)
        ->and($this->queue->size('default'))->toBe(0);
    Mail::assertNothingQueued();
});

test('a post without a Cover makes no thumbnail request and no job', function () {
    ['post' => $post] = facebookVideoDueNow($this->channel, ContentType::FacebookPost, withCover: false);
    fakeFacebook();

    publishFacebookThroughPipeline();
    expect($this->queue->size('default'))->toBe(0);

    $this->travel(10)->minutes();
    runDueFacebookCoverJobs();

    expect($post->refresh()->status)->toBe(PostStatus::Published)
        ->and(facebookThumbnailRequests())->toBeEmpty()
        ->and(CoverOutcome::query()->count())->toBe(0);
});

test('a refused Cover is recorded at once without trying again', function (int $status) {
    ['post' => $post] = facebookVideoDueNow($this->channel, ContentType::FacebookReel);
    fakeFacebook(['ready'], thumbnailStatus: $status);

    publishFacebookThroughPipeline();
    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueFacebookCoverJobs();

    expect(CoverOutcome::query()->sole()->outcome)->toBe(Outcome::Failed)
        ->and(CoverOutcome::query()->sole()->attempts)->toBe(1)
        ->and(facebookThumbnailRequests())->toHaveCount(1)
        ->and($post->refresh()->status)->toBe(PostStatus::Published)
        ->and($this->queue->size('default'))->toBe(0);
})->with([400, 403]);

test('a video Facebook failed to process is recorded as failed without a thumbnail request', function (string $videoStatus) {
    facebookVideoDueNow($this->channel, ContentType::FacebookPost);
    fakeFacebook([$videoStatus]);

    publishFacebookThroughPipeline();
    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueFacebookCoverJobs();

    expect(CoverOutcome::query()->sole()->outcome)->toBe(Outcome::Failed)
        ->and(facebookThumbnailRequests())->toBeEmpty();
})->with(['error', 'expired']);

test('a busy Facebook is tried again and the Cover is set once it answers', function () {
    facebookVideoDueNow($this->channel, ContentType::FacebookPost);
    fakeFacebook(['ready'], thumbnailStatus: [503, 200]);

    publishFacebookThroughPipeline();
    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueFacebookCoverJobs();
    expect(CoverOutcome::query()->count())->toBe(0);

    $this->travel(SetPostCover::BACKOFF_SECONDS[0])->seconds();
    runDueFacebookCoverJobs();

    expect(CoverOutcome::query()->sole()->outcome)->toBe(Outcome::Set)
        ->and(facebookThumbnailRequests())->toHaveCount(2);
});

test('a Cover already set is not sent again', function () {
    ['post' => $post] = facebookVideoDueNow($this->channel, ContentType::FacebookPost);
    fakeFacebook();
    publishFacebookThroughPipeline();
    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueFacebookCoverJobs();

    (new SetPostCover($post->id))->handle();

    expect(facebookThumbnailRequests())->toHaveCount(1);
});

test('Facebook video posts and Reels take a Cover image, Stories do not', function (ContentType $type, bool $takesCover) {
    expect($type->supportsCoverImage())->toBe($takesCover)
        ->and($type->mediaRules()['supports_cover_image'])->toBe($takesCover);
})->with([
    'a video post' => [ContentType::FacebookPost, true],
    'a Reel' => [ContentType::FacebookReel, true],
    'a Story' => [ContentType::FacebookStory, false],
]);

/**
 * Saves a Facebook draft whose first media item carries a Cover image, for a
 * member of its own workspace. `$shape` is `video`, `carousel` (the video and
 * an image) or `image`.
 */
function saveFacebookDraftWithCover(object $test, ContentType $type, string $shape = 'video'): TestResponse
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $channel = SocialAccount::factory()->facebook()->create(['workspace_id' => $workspace->id]);
    $post = Post::factory()->forAccount($channel, $type)->create(['user_id' => $user->id, 'status' => PostStatus::Draft, 'scheduled_at' => null]);
    $video = Media::factory()->video()->ownedByPost($post)->stored()->create(['meta' => ['duration' => 10]]);
    $image = Media::factory()->ownedByPost($post)->stored()->create();
    $cover = Media::factory()->temporaryUpload($workspace)->stored()->create([
        'type' => 'image',
        'mime_type' => 'image/jpeg',
        'original_filename' => 'cover.jpg',
        'size' => 500_000,
    ]);
    $coverMeta = ['cover_image' => MediaItem::fromMedia($cover)->toArray()];
    $withCover = fn (Media $media): array => [...MediaItem::fromMedia($media)->toArray(), 'meta' => [...(array) data_get(MediaItem::fromMedia($media)->toArray(), 'meta', []), ...$coverMeta]];
    $items = match ($shape) {
        'image' => [$withCover($image)],
        'carousel' => [$withCover($video), MediaItem::fromMedia($image)->toArray()],
        default => [$withCover($video)],
    };

    $response = $test->actingAs($user)->put(route('app.posts.update', $post), [
        'status' => 'draft',
        'content' => 'hi',
        'content_type' => $type->value,
        'media' => $items,
    ]);

    $saved = collect($post->fresh()->media)->first();
    $test->savedCoverId = transform($saved, fn (array $item): ?string => MediaItem::fromArray($item)->coverImage()?->id);
    $test->coverId = $cover->id;

    return $response;
}

test('a Facebook video post and a Reel keep the Cover image they are saved with', function (ContentType $type) {
    saveFacebookDraftWithCover($this, $type)->assertSessionHasNoErrors();

    expect($this->savedCoverId)->toBe($this->coverId);
})->with([
    'a video post' => [ContentType::FacebookPost],
    'a Reel' => [ContentType::FacebookReel],
]);

test('a Cover image is refused on a Facebook post that cannot take one', function (ContentType $type, string $shape) {
    saveFacebookDraftWithCover($this, $type, $shape)
        ->assertSessionHasErrors(['media.0.meta.cover_image' => "This post type can't use a Cover image. Remove the Cover image, then try again."]);

    expect($this->savedCoverId)->toBeNull();
})->with([
    'a Story' => [ContentType::FacebookStory, 'video'],
    'a post with several media' => [ContentType::FacebookPost, 'carousel'],
    'a post with an image' => [ContentType::FacebookPost, 'image'],
]);
