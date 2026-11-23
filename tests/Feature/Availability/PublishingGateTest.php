<?php

declare(strict_types=1);

use App\Actions\Post\RetryBlockedPost;
use App\Enums\Account\AccessDenial;
use App\Enums\Post\PublishStatus;
use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Jobs\PublishPost;
use App\Jobs\PublishToSocialPlatform;
use App\Jobs\SendNotification;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Availability\Availability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);

    Http::fake();
    Mail::fake();
    Queue::fake();

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
    $this->workspace->members()->attach($this->user->id, membershipPivot('admin'));
    $this->account = $this->workspace->account;
    subscribeAccount($this->account);

    $this->channel = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $this->post = Post::factory()->forAccount($this->channel)->scheduled()->create([
        'user_id' => $this->user->id,
    ]);
});

function availabilitySwitch(bool $enabled): void
{
    app(Availability::class)->set(Platform::LinkedIn->availabilityKey(), $enabled);
}

test('a network switched off blocks a due post with its own reason', function () {
    availabilitySwitch(false);

    (new PublishPost($this->post))->handle();

    $post = $this->post->fresh();

    expect($post->status)->toBe(PostStatus::Failed)
        ->and($post->publish_status)->toBe(PublishStatus::Failed)
        ->and($post->publish_block_reason)->toBe(AccessDenial::Unavailable)
        ->and($post->error_message)->toBeNull();

    Queue::assertNotPushed(PublishToSocialPlatform::class);
    Http::assertNothingSent();
});

test('a retry while the network is still off blocks the post again', function () {
    availabilitySwitch(false);

    $post = Post::factory()->forAccount($this->channel)->failed()->create([
        'user_id' => $this->user->id,
        'publish_block_reason' => AccessDenial::Unavailable->value,
    ]);

    RetryBlockedPost::execute($post);

    Queue::assertPushed(PublishPost::class);
    expect($post->fresh()->publish_block_reason)->toBeNull()
        ->and($post->fresh()->status)->toBe(PostStatus::Publishing);

    (new PublishPost($post->fresh()))->handle();

    expect($post->fresh()->status)->toBe(PostStatus::Failed)
        ->and($post->fresh()->publish_block_reason)->toBe(AccessDenial::Unavailable);

    Queue::assertNotPushed(PublishToSocialPlatform::class);
});

test('a retry after the network is back clears the block and dispatches the publish', function () {
    availabilitySwitch(false);

    $post = Post::factory()->forAccount($this->channel)->failed()->create([
        'user_id' => $this->user->id,
        'publish_block_reason' => AccessDenial::Unavailable->value,
    ]);

    availabilitySwitch(true);

    RetryBlockedPost::execute($post);

    $post->refresh();

    expect($post->publish_block_reason)->toBeNull()
        ->and($post->publish_status)->toBe(PublishStatus::Pending)
        ->and($post->status)->toBe(PostStatus::Publishing);

    Queue::assertPushed(PublishPost::class);

    (new PublishPost($post))->handle();

    Queue::assertPushed(PublishToSocialPlatform::class);
});

test('a network switched off sends no failure email', function () {
    availabilitySwitch(false);

    (new PublishPost($this->post))->handle();

    expect($this->post->fresh()->publish_block_reason)->toBe(AccessDenial::Unavailable);
    Queue::assertNotPushed(SendNotification::class);
});
