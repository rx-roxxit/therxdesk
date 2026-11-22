<?php

declare(strict_types=1);

use App\Enums\Account\AccessDenial;
use App\Enums\Post\PublishStatus;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Jobs\PublishToSocialPlatform;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
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

    $this->channel = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $this->account = $this->workspace->account;

    $this->post = Post::factory()->forAccount($this->channel)->scheduled()->create([
        'user_id' => $this->user->id,
    ]);
});

test('a lapsed account stops a scheduled post before the network', function () {
    (new PublishPost($this->post))->handle();

    $post = $this->post->fresh();

    expect($post->status)->toBe(PostStatus::Failed)
        ->and($post->publish_status)->toBe(PublishStatus::Failed)
        ->and($post->publish_block_reason)->toBe(AccessDenial::Lapsed)
        ->and($post->error_message)->toBeNull();

    Queue::assertNotPushed(PublishToSocialPlatform::class);
    Http::assertNothingSent();
});

test('a suspended account stops a scheduled post with its own reason', function () {
    $this->account->forceFill(['suspended_at' => now()])->save();

    (new PublishPost($this->post))->handle();

    $post = $this->post->fresh();

    expect($post->status)->toBe(PostStatus::Failed)
        ->and($post->publish_status)->toBe(PublishStatus::Failed)
        ->and($post->publish_block_reason)->toBe(AccessDenial::Suspended);

    Queue::assertNotPushed(PublishToSocialPlatform::class);
    Http::assertNothingSent();
});

test('a direct retry dispatch is stopped by the gate', function () {
    (new PublishToSocialPlatform($this->post))->handle();

    $post = $this->post->fresh();

    expect($post->status)->toBe(PostStatus::Failed)
        ->and($post->publish_block_reason)->toBe(AccessDenial::Lapsed);

    Http::assertNothingSent();
});

test('a limit retry dispatch is stopped by the gate', function () {
    $this->post->forceFill([
        'publish_status' => PublishStatus::Retrying,
        'retry_at' => now()->subMinute(),
        'error_message' => 'Rate limited',
    ])->save();

    (new PublishToSocialPlatform($this->post->fresh()))->handle();

    $post = $this->post->fresh();

    expect($post->publish_status)->toBe(PublishStatus::Failed)
        ->and($post->publish_block_reason)->toBe(AccessDenial::Lapsed)
        ->and($post->retry_at)->toBeNull();

    Http::assertNothingSent();
});

test('restored access lets the post publish again', function () {
    subscribeAccount($this->account);

    (new PublishPost($this->post))->handle();

    $post = $this->post->fresh();

    expect($post->status)->toBe(PostStatus::Publishing)
        ->and($post->publish_block_reason)->toBeNull();

    Queue::assertPushed(PublishToSocialPlatform::class);
});
