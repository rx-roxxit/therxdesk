<?php

declare(strict_types=1);

use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AlertLevel;
use App\Enums\Post\PublishStatus;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Jobs\PublishToSocialPlatform;
use App\Mail\AdminAlert as AdminAlertMail;
use App\Models\AdminAlert;
use App\Models\NetworkCallBucket;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\NetworkLimits\NetworkLimits;
use App\Services\Social\TikTokPublisher;
use App\Support\Social\LimitRetryPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    Event::fake();
    Queue::fake();

    $this->travelTo(CarbonImmutable::now('UTC')->startOfDay()->addHours(23));

    config([
        'trypost.self_hosted' => false,
        'rxdesk.network_limits.enabled' => true,
        'rxdesk.network_limits.buckets.tiktok' => 10,
        'rxdesk.alerts.recipient' => 'admin@example.com',
    ]);

    $this->user = User::factory()->create();
    subscribeAccount($this->user->account);
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
});

function networkBucketTarget(object $test): Post
{
    $account = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $test->workspace->id,
    ]);

    return Post::factory()->forAccount($account, ContentType::defaultFor(Platform::TikTok))->create([
        'user_id' => $test->user->id,
        'status' => PostStatus::Publishing,
        'scheduled_at' => now()->subMinute(),
        'publish_status' => PublishStatus::Pending,
        'scheduled_before_media_checks' => true,
    ]);
}

test('an exhausted bucket keeps the post retrying until the UTC reset and never calls the network', function () {
    NetworkCallBucket::factory()->create([
        'network' => 'tiktok',
        'calls' => 10,
        'exhausted_at' => now(),
    ]);
    $post = networkBucketTarget($this);
    $publisher = Mockery::mock(TikTokPublisher::class);
    $publisher->shouldNotReceive('publish');
    $this->app->instance(TikTokPublisher::class, $publisher);

    (new PublishToSocialPlatform($post))->handle();

    $post->refresh();

    expect($post->publish_status)->toBe(PublishStatus::Retrying)
        ->and($post->retry_at->toIso8601String())->toBe(app(NetworkLimits::class)->resetAt()->toIso8601String())
        ->and($post->error_message)->toBe(__('rxdesk.publishing_block.network_limit', ['network' => Platform::TikTok->label()]))
        ->and(data_get($post->error_context, 'category'))->toBe('network_bucket_exhausted')
        ->and(data_get($post->error_context, 'network'))->toBe('tiktok')
        ->and(data_get($post->error_context, LimitRetryPolicy::ATTEMPTS_KEY))->toBeNull();

    expect(AdminAlert::query()->where('key', 'network_bucket_exhausted:tiktok')->sole()->is_open)->toBeTrue();
    Mail::assertQueued(AdminAlertMail::class, fn (AdminAlertMail $mail): bool => $mail->level === AlertLevel::Urgent
        && $mail->key === AdminAlertKey::NetworkBucketExhausted);
});

test('a bucket with room left publishes normally', function () {
    NetworkCallBucket::factory()->create(['network' => 'tiktok', 'calls' => 5]);
    $post = networkBucketTarget($this);
    $publisher = Mockery::mock(TikTokPublisher::class);
    $publisher->shouldReceive('publish')->once()->andReturn([
        'id' => 'tt-1',
        'url' => 'https://www.tiktok.com/@demo/video/1',
    ]);
    $this->app->instance(TikTokPublisher::class, $publisher);

    (new PublishToSocialPlatform($post))->handle();

    expect($post->refresh()->publish_status)->toBe(PublishStatus::Published);
});

test('raising the capacity lets the same bucket publish again', function () {
    NetworkCallBucket::factory()->create([
        'network' => 'tiktok',
        'calls' => 10,
        'exhausted_at' => now(),
    ]);
    $post = networkBucketTarget($this);
    config(['rxdesk.network_limits.buckets.tiktok' => 20]);

    $publisher = Mockery::mock(TikTokPublisher::class);
    $publisher->shouldReceive('publish')->once()->andReturn([
        'id' => 'tt-3',
        'url' => 'https://www.tiktok.com/@demo/video/3',
    ]);
    $this->app->instance(TikTokPublisher::class, $publisher);

    (new PublishToSocialPlatform($post))->handle();

    expect($post->refresh()->publish_status)->toBe(PublishStatus::Published)
        ->and(app(NetworkLimits::class)->isExhausted('tiktok'))->toBeFalse();
});

test('after the UTC reset a fresh bucket publishes the waiting post', function () {
    NetworkCallBucket::factory()->create([
        'network' => 'tiktok',
        'calls' => 10,
        'exhausted_at' => now(),
    ]);
    $post = networkBucketTarget($this);

    $blocked = Mockery::mock(TikTokPublisher::class);
    $blocked->shouldNotReceive('publish');
    $this->app->instance(TikTokPublisher::class, $blocked);

    (new PublishToSocialPlatform($post))->handle();

    expect($post->refresh()->publish_status)->toBe(PublishStatus::Retrying);

    $this->travelTo(now()->addMinutes(70));

    $publisher = Mockery::mock(TikTokPublisher::class);
    $publisher->shouldReceive('publish')->once()->andReturn([
        'id' => 'tt-2',
        'url' => 'https://www.tiktok.com/@demo/video/2',
    ]);
    $this->app->instance(TikTokPublisher::class, $publisher);

    (new PublishToSocialPlatform($post->fresh()))->handle();

    expect($post->refresh()->publish_status)->toBe(PublishStatus::Published)
        ->and(app(NetworkLimits::class)->isExhausted('tiktok'))->toBeFalse();
});
