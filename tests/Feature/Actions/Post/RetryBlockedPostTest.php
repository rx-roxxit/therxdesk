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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);

    Mail::fake();
    Queue::fake();

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->owner->id,
        'account_id' => $this->owner->account_id,
    ]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->account = $this->workspace->account;
    subscribeAccount($this->account);

    $this->channel = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
    ]);
});

test('the retry command clears the block and dispatches the network job after access returns', function () {
    $post = Post::factory()->forAccount($this->channel)->failed()->create([
        'user_id' => $this->owner->id,
        'publish_block_reason' => AccessDenial::Suspended->value,
    ]);

    $this->artisan('posts:retry', ['post' => $post->id])
        ->expectsConfirmation('Queue a publish attempt for this post?', 'yes')
        ->assertSuccessful();

    $post->refresh();

    expect($post->publish_block_reason)->toBeNull()
        ->and($post->publish_status)->toBe(PublishStatus::Pending)
        ->and($post->status)->toBe(PostStatus::Publishing);

    Queue::assertPushed(PublishToSocialPlatform::class);
});

test('the retry route refuses a failed post that the access gate did not block', function () {
    $post = Post::factory()->forAccount($this->channel)->failed()->create([
        'user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->from(route('app.posts.index'))
        ->post(route('app.posts.retry', $post))
        ->assertRedirect(route('app.posts.index'))
        ->assertSessionHas('flash.banner', __('rxdesk.publishing_block.retry_failed'))
        ->assertSessionHas('flash.bannerStyle', 'danger');

    expect($post->fresh()->status)->toBe(PostStatus::Failed);

    Queue::assertNotPushed(PublishPost::class);
});

test('the retry route requeues a blocked post once access is restored', function () {
    $this->account->forceFill([
        'suspended_at' => now(),
        'suspension_reason' => 'Chargeback investigation',
    ])->save();

    $post = Post::factory()->forAccount($this->channel)->failed()->create([
        'user_id' => $this->owner->id,
        'publish_block_reason' => AccessDenial::Suspended->value,
    ]);

    $this->account->forceFill([
        'suspended_at' => null,
        'suspension_reason' => null,
    ])->save();
    $this->owner->refresh();

    $this->actingAs($this->owner)
        ->from(route('app.posts.index'))
        ->post(route('app.posts.retry', $post))
        ->assertRedirect(route('app.posts.index'))
        ->assertSessionHas('flash.banner', __('rxdesk.publishing_block.retry_started'))
        ->assertSessionHas('flash.bannerStyle', 'success');

    $post->refresh();

    expect($post->status)->toBe(PostStatus::Publishing)
        ->and($post->publish_status)->toBe(PublishStatus::Pending)
        ->and($post->publish_block_reason)->toBeNull();

    Queue::assertPushed(PublishPost::class);
});

test('a member who needs approval cannot retry a blocked post', function () {
    $member = workspaceMember($this->workspace, 'approval');

    $post = Post::factory()->forAccount($this->channel)->failed()->create([
        'user_id' => $this->owner->id,
        'publish_block_reason' => AccessDenial::Lapsed->value,
    ]);

    $this->actingAs($member)
        ->post(route('app.posts.retry', $post))
        ->assertForbidden();

    expect($post->fresh()->status)->toBe(PostStatus::Failed);

    Queue::assertNotPushed(PublishPost::class);
});
