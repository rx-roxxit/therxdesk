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
use Google\Client;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

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

    $this->channel = SocialAccount::factory()->youtube()->create([
        'platform_user_id' => 'UC_cover',
        'token_expires_at' => now()->addDays(7),
        'meta' => ['channel_id' => 'UC_cover'],
    ]);
    $this->data = config('trypost.platforms.youtube.data_api');
    $this->upload = config('trypost.platforms.youtube.upload_api');
});

/**
 * A YouTube Short due now, with a Cover image on its video when `$withCover`.
 *
 * @return array{post: Post, cover: Media|null}
 */
function youtubeShortDueNow(SocialAccount $channel, bool $withCover = true): array
{
    $post = Post::factory()->forAccount($channel, ContentType::YouTubeShort)->scheduled()->create([
        'user_id' => $channel->workspace->user_id,
        'scheduled_at' => now()->subMinute(),
        'content' => 'Cover short',
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
 * Fakes the upload and the Cover calls; `$processing` lists what each
 * `videos.list` read answers, in order, the last one repeating.
 *
 * @param  list<string>  $processing
 * @param  int|list<int>  $thumbnailStatus  what `thumbnails.set` answers, in order, the last one repeating
 */
function fakeYouTube(array $processing = ['succeeded'], int|array $thumbnailStatus = 200): void
{
    $reads = 0;
    $thumbnails = 0;
    $statuses = (array) $thumbnailStatus;
    $data = config('trypost.platforms.youtube.data_api');
    $upload = config('trypost.platforms.youtube.upload_api');

    Http::fake(function (Request $request) use ($processing, $statuses, $data, $upload, &$reads, &$thumbnails) {
        $url = $request->url();

        if (str_starts_with($url, "{$data}/videos")) {
            $status = $processing[min($reads++, count($processing) - 1)];

            return Http::response(['items' => [['id' => 'short-id', 'processingDetails' => ['processingStatus' => $status]]]]);
        }

        if (str_starts_with($url, "{$upload}/thumbnails/set")) {
            $status = $statuses[min($thumbnails++, count($statuses) - 1)];

            return $status === 200
                ? Http::response(['items' => [['default' => ['url' => 'https://i.ytimg.test/default.jpg']]]])
                : Http::response(['error' => ['code' => $status, 'errors' => [['reason' => 'forbidden']]]], $status);
        }

        if (str_starts_with($url, 'https://youtube.googleapis.com/upload/youtube/v3/videos')) {
            return Http::response('', 200, ['Location' => 'https://upload.example.test/session']);
        }

        if ($url === 'https://upload.example.test/session') {
            return Http::response(['id' => 'short-id']);
        }

        return Http::response(str_repeat('v', 4096));
    });

    $client = new Client;
    $client->setHttpClient(Http::buildClient());
    test()->instance(Client::class, $client);
}

/**
 * Runs the scheduler and the publish jobs the way the workers would.
 */
function publishYouTubeThroughPipeline(): void
{
    test()->artisan('posts:process-scheduled')->assertSuccessful();
    test()->worker->runNextJob('database', 'default', test()->workerOptions);
    test()->worker->runNextJob('database', Platform::YouTube->queue(), test()->workerOptions);
}

/**
 * Runs every Cover job that is due.
 */
function runDueCoverJobs(): void
{
    while (test()->queue->pendingSize('default') > 0) {
        test()->worker->runNextJob('database', 'default', test()->workerOptions);
    }

    test()->assertDatabaseCount('failed_jobs', 0);
}

/**
 * @return list<Request>
 */
function sentTo(string $prefix): array
{
    return Http::recorded(fn (Request $request): bool => str_starts_with($request->url(), $prefix))
        ->map(fn (array $pair): Request => $pair[0])
        ->values()
        ->all();
}

test('a published Short sends its Cover to YouTube once processing has succeeded', function () {
    ['post' => $post] = youtubeShortDueNow($this->channel);
    fakeYouTube(['processing', 'succeeded']);

    publishYouTubeThroughPipeline();
    expect($post->refresh()->status)->toBe(PostStatus::Published)
        ->and($post->publish_status)->toBe(PublishStatus::Published);
    expect(sentTo("{$this->upload}/thumbnails/set"))->toBeEmpty();

    $this->travel(1)->minutes();
    runDueCoverJobs();
    expect(sentTo("{$this->upload}/thumbnails/set"))->toBeEmpty()
        ->and(CoverOutcome::query()->count())->toBe(0);

    $this->travel(2)->minutes();
    runDueCoverJobs();

    $sent = sentTo("{$this->upload}/thumbnails/set");

    expect($sent)->toHaveCount(1)
        ->and($sent[0]->method())->toBe('POST')
        ->and($sent[0]->hasHeader('Authorization', "Bearer {$this->channel->access_token}"))->toBeTrue()
        ->and($sent[0]->url())->toContain('videoId=short-id')
        ->and($sent[0]->header('Content-Type')[0])->toBe('image/png')
        ->and($sent[0]->body())->toBe('png bytes')
        ->and(CoverOutcome::query()->sole()->outcome)->toBe(Outcome::Set);
});

test('while YouTube keeps processing the job tries for about 30 minutes, then records the failure and the post stays published', function () {
    ['post' => $post] = youtubeShortDueNow($this->channel);
    fakeYouTube(['processing']);

    publishYouTubeThroughPipeline();

    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueCoverJobs();

    foreach (SetPostCover::BACKOFF_SECONDS as $seconds) {
        expect(CoverOutcome::query()->count())->toBe(0);

        $this->travel($seconds)->seconds();
        runDueCoverJobs();
    }

    $outcome = CoverOutcome::query()->sole();

    expect($outcome->outcome)->toBe(Outcome::Failed)
        ->and($outcome->post_id)->toBe($post->id)
        ->and($outcome->attempts)->toBe(8)
        ->and(SetPostCover::FIRST_DELAY_SECONDS + array_sum(SetPostCover::BACKOFF_SECONDS))->toBeBetween(1700, 1900)
        ->and(sentTo("{$this->upload}/thumbnails/set"))->toBeEmpty()
        ->and(sentTo("{$this->data}/videos"))->toHaveCount(8)
        ->and($post->refresh()->status)->toBe(PostStatus::Published)
        ->and($this->queue->size('default'))->toBe(0);
    Mail::assertNothingQueued();
});

test('a post without a Cover makes no thumbnail request and no job', function () {
    ['post' => $post] = youtubeShortDueNow($this->channel, withCover: false);
    fakeYouTube();

    publishYouTubeThroughPipeline();
    expect($this->queue->size('default'))->toBe(0);

    $this->travel(10)->minutes();
    runDueCoverJobs();

    expect($post->refresh()->status)->toBe(PostStatus::Published)
        ->and(sentTo("{$this->upload}/thumbnails/set"))->toBeEmpty()
        ->and(sentTo("{$this->data}/videos"))->toBeEmpty()
        ->and(CoverOutcome::query()->count())->toBe(0);
});

test('a refused Cover is recorded at once without trying again', function (int $status) {
    ['post' => $post] = youtubeShortDueNow($this->channel);
    fakeYouTube(['succeeded'], thumbnailStatus: $status);

    publishYouTubeThroughPipeline();
    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueCoverJobs();

    expect(CoverOutcome::query()->sole()->outcome)->toBe(Outcome::Failed)
        ->and(CoverOutcome::query()->sole()->attempts)->toBe(1)
        ->and(sentTo("{$this->upload}/thumbnails/set"))->toHaveCount(1)
        ->and($post->refresh()->status)->toBe(PostStatus::Published)
        ->and($this->queue->size('default'))->toBe(0);
})->with([400, 403, 404]);

test('a video YouTube failed to process is recorded as failed without a thumbnail request', function () {
    youtubeShortDueNow($this->channel);
    fakeYouTube(['failed']);

    publishYouTubeThroughPipeline();
    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueCoverJobs();

    expect(CoverOutcome::query()->sole()->outcome)->toBe(Outcome::Failed)
        ->and(sentTo("{$this->upload}/thumbnails/set"))->toBeEmpty();
});

test('a busy YouTube is tried again and the Cover is set once it answers', function () {
    youtubeShortDueNow($this->channel);
    fakeYouTube(['succeeded'], thumbnailStatus: [503, 200]);

    publishYouTubeThroughPipeline();
    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueCoverJobs();
    expect(CoverOutcome::query()->count())->toBe(0);

    $this->travel(SetPostCover::BACKOFF_SECONDS[0])->seconds();
    runDueCoverJobs();

    expect(CoverOutcome::query()->sole()->outcome)->toBe(Outcome::Set)
        ->and(sentTo("{$this->upload}/thumbnails/set"))->toHaveCount(2);
});

test('a Cover already set is not sent again', function () {
    ['post' => $post] = youtubeShortDueNow($this->channel);
    fakeYouTube();
    publishYouTubeThroughPipeline();
    $this->travel(SetPostCover::FIRST_DELAY_SECONDS)->seconds();
    runDueCoverJobs();

    (new SetPostCover($post->id))->handle();

    expect(sentTo("{$this->upload}/thumbnails/set"))->toHaveCount(1);
});

test('a post that is not published gets no Cover', function () {
    ['post' => $post] = youtubeShortDueNow($this->channel);
    $post->forceFill(['status' => PostStatus::Failed, 'platform_post_id' => 'short-id'])->save();
    fakeYouTube();

    (new SetPostCover($post->id))->handle();

    expect(Http::recorded())->toBeEmpty()
        ->and(CoverOutcome::query()->count())->toBe(0);
});

test('a network without a Cover setter starts no job', function () {
    $telegram = SocialAccount::factory()->telegram()->create();
    $post = Post::factory()->forAccount($telegram)->scheduled()->create([
        'user_id' => $telegram->workspace->user_id,
        'scheduled_at' => now()->subMinute(),
    ]);
    $cover = Media::factory()->ownedByPost($post)->create(['mime_type' => 'image/png']);
    $post->update(['media' => [['id' => 'v', 'type' => 'video', 'meta' => ['cover_image' => MediaItem::fromMedia($cover)->toArray()]]]]);
    Http::fake();

    $post->update(['status' => PostStatus::Published]);
    $this->travel(10)->minutes();

    expect($this->queue->size('default'))->toBe(0);
});

test('the post details carry the Cover outcome', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $channel = SocialAccount::factory()->youtube()->create(['workspace_id' => $workspace->id]);
    $post = Post::factory()->forAccount($channel, ContentType::YouTubeShort)->create([
        'user_id' => $user->id,
        'status' => PostStatus::Published,
        'publish_status' => PublishStatus::Published,
        'published_at' => now()->subHour(),
    ]);
    CoverOutcome::factory()->create(['post_id' => $post->id, 'last_error' => 'internal detail']);

    $this->actingAs($user)
        ->getJson(route('app.posts.group.show', $post))
        ->assertOk()
        ->assertJsonPath('0.cover_outcome.outcome', 'failed')
        ->assertJsonMissingPath('0.cover_outcome.last_error')
        ->assertJsonMissingPath('0.cover_outcome.attempts');

    $this->actingAs($user)
        ->get(route('app.posts.index', ['tab' => 'sent']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('posts.data.0.cover_outcome.outcome', 'failed')));
});

test('a YouTube Short takes a Cover image', function () {
    expect(ContentType::YouTubeShort->supportsCoverImage())->toBeTrue()
        ->and(ContentType::YouTubeShort->mediaRules()['supports_cover_image'])->toBeTrue();
});

/**
 * Fakes a YouTube whose first call to `$expiredAt` ("videos" or "thumbnails")
 * answers 401, with the token endpoint answering `$refresh`; everything else
 * succeeds, and the Cover is accepted only with the refreshed token.
 *
 * @param  array<string, mixed>|null  $refreshBody
 */
function fakeYouTubeWithExpiredToken(string $expiredAt, int $refreshStatus = 200, ?array $refreshBody = null, bool $stillExpired = false): void
{
    $data = config('trypost.platforms.youtube.data_api');
    $upload = config('trypost.platforms.youtube.upload_api');
    $oauth = config('trypost.platforms.youtube.oauth_api');
    $refreshBody ??= ['access_token' => 'fresh-token', 'expires_in' => 3600];

    Http::fake(function (Request $request) use ($expiredAt, $refreshStatus, $refreshBody, $stillExpired, $data, $upload, $oauth) {
        $url = $request->url();
        $fresh = $request->hasHeader('Authorization', 'Bearer fresh-token');

        if ($url === "{$oauth}/token") {
            return Http::response($refreshBody, $refreshStatus);
        }

        if (str_starts_with($url, "{$data}/videos")) {
            if ($expiredAt === 'videos' && (! $fresh || $stillExpired)) {
                return Http::response(['error' => ['code' => 401, 'errors' => [['reason' => 'authError']]]], 401);
            }

            return Http::response(['items' => [['processingDetails' => ['processingStatus' => 'succeeded']]]]);
        }

        if (str_starts_with($url, "{$upload}/thumbnails/set")) {
            if ($expiredAt === 'thumbnails' && (! $fresh || $stillExpired)) {
                return Http::response(['error' => ['code' => 401, 'errors' => [['reason' => 'authError']]]], 401);
            }

            return Http::response(['items' => []]);
        }

        return Http::response('', 500);
    });
}

test('an access token that expired early is refreshed once and the Cover is sent with the new one', function (string $expiredAt) {
    youtubeShortDueNow($this->channel);
    $this->channel->update(['refresh_token' => 'refresh-me']);
    fakeYouTubeWithExpiredToken($expiredAt);
    $post = Post::query()->sole()->forceFill(['status' => PostStatus::Published, 'platform_post_id' => 'short-id']);
    $post->saveQuietly();

    (new SetPostCover($post->id))->handle();

    $oauth = config('trypost.platforms.youtube.oauth_api');
    $sent = sentTo("{$this->upload}/thumbnails/set");

    expect(sentTo("{$oauth}/token"))->toHaveCount(1)
        ->and(CoverOutcome::query()->sole()->outcome)->toBe(Outcome::Set)
        ->and(end($sent)->hasHeader('Authorization', 'Bearer fresh-token'))->toBeTrue()
        ->and($this->channel->refresh()->access_token)->toBe('fresh-token');
})->with(['the processing read' => 'videos', 'the thumbnail upload' => 'thumbnails']);

test('a token that is still refused after one refresh is not a refusal: the job looks again later', function () {
    youtubeShortDueNow($this->channel);
    $this->channel->update(['refresh_token' => 'refresh-me']);
    fakeYouTubeWithExpiredToken('thumbnails', stillExpired: true);
    $post = Post::query()->sole()->forceFill(['status' => PostStatus::Published, 'platform_post_id' => 'short-id']);
    $post->saveQuietly();

    $job = new SetPostCover($post->id);
    $job->withFakeQueueInteractions();
    $job->handle();

    $oauth = config('trypost.platforms.youtube.oauth_api');

    $job->assertReleased(SetPostCover::BACKOFF_SECONDS[0]);
    expect(sentTo("{$oauth}/token"))->toHaveCount(1)
        ->and(CoverOutcome::query()->count())->toBe(0);
});

test('a refresh that fails for now is tried again, one Google rejects as revoked is final', function (int $status, ?array $body, ?Outcome $outcome) {
    youtubeShortDueNow($this->channel);
    $this->channel->update(['refresh_token' => 'refresh-me']);
    fakeYouTubeWithExpiredToken('thumbnails', refreshStatus: $status, refreshBody: $body);
    $post = Post::query()->sole()->forceFill(['status' => PostStatus::Published, 'platform_post_id' => 'short-id']);
    $post->saveQuietly();

    $job = new SetPostCover($post->id);
    $job->withFakeQueueInteractions();
    $job->handle();

    expect(CoverOutcome::query()->first()?->outcome)->toBe($outcome);

    if ($outcome === null) {
        $job->assertReleased(SetPostCover::BACKOFF_SECONDS[0]);
    }
})->with([
    'Google is busy' => [503, ['error' => 'backend_error'], null],
    'the grant was revoked' => [400, ['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], Outcome::Failed],
]);
