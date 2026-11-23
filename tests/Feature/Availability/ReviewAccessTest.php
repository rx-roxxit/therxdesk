<?php

declare(strict_types=1);

use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Jobs\PublishPost;
use App\Jobs\PublishToSocialPlatform;
use App\Models\Account;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Availability\Availability;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
    $this->workspace->members()->attach($this->user->id, membershipPivot('admin'));
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->account = $this->user->account;
    $this->channel = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
    ]);
});

function reviewAccessPlatformsOff(): void
{
    app(Availability::class)->set(Platform::LinkedIn->availabilityKey(), false);
    app(Availability::class)->set(Platform::LinkedInPage->availabilityKey(), false);
}

function reviewAccessGrant(Account $account, bool $enabled = true): Account
{
    $account->forceFill(['review_access' => $enabled])->save();

    return $account->fresh();
}

test('Review access keeps a switched-off network in the connect cards and out of the unavailable list', function () {
    reviewAccessPlatformsOff();
    $account = reviewAccessGrant($this->account);

    $this->actingAs($this->user)
        ->get(route('app.workspace.channels'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('connectablePlatforms', fn ($platforms): bool => in_array('linkedin', collect($platforms)->pluck('value')->all(), true))
            ->where('unavailablePlatforms', fn ($platforms): bool => ! in_array('linkedin', collect($platforms)->all(), true))
        );

    expect(Platform::LinkedIn->isAvailableFor($account))->toBeTrue()
        ->and(Platform::LinkedIn->isConnectableFor($account))->toBeTrue()
        ->and(Platform::LinkedInPage->isConnectableFor($account))->toBeFalse()
        ->and(Platform::LinkedIn->isAvailable())->toBeFalse();
});

test('Review access belongs to one Account only', function () {
    reviewAccessPlatformsOff();
    $reviewer = reviewAccessGrant($this->account);
    $ordinary = Account::factory()->create();

    expect(Platform::LinkedIn->isAvailableFor($reviewer))->toBeTrue()
        ->and(Platform::LinkedIn->isAvailableFor($ordinary))->toBeFalse();
});

test('Review access lets the connect confirmation render while the network is off', function () {
    reviewAccessPlatformsOff();
    reviewAccessGrant($this->account);
    startSocialConnect($this->workspace, Platform::LinkedIn);

    $this->actingAs($this->user)
        ->get(route('app.social.connect.show', Platform::LinkedIn))
        ->assertOk();
});

test('Review access lets a scheduled post save on a switched-off network', function () {
    reviewAccessPlatformsOff();
    reviewAccessGrant($this->account);

    $this->actingAs($this->user)
        ->post(route('app.posts.store'), [
            'status' => 'scheduled',
            'content' => 'Review access schedule',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'destinations' => [[
                'social_account_id' => $this->channel->id,
                'content_type' => 'linkedin_post',
            ]],
        ])
        ->assertSessionHasNoErrors();

    expect(Post::query()->where('social_account_id', $this->channel->id)->count())->toBe(1);
});

test('Review access lets publish now pass the stored-post gate on a switched-off network', function () {
    Queue::fake();
    reviewAccessPlatformsOff();
    reviewAccessGrant($this->account);
    $post = Post::factory()->forAccount($this->channel)->draft()->create([
        'user_id' => $this->user->id,
        'content' => 'Review access publish now',
    ]);

    $this->actingAs($this->user)
        ->put(route('app.posts.schedule.update', $post), ['action' => 'publish_now'])
        ->assertSessionHasNoErrors();

    expect($post->fresh()->status)->toBe(PostStatus::Publishing);
});

test('Review access lets a due post publish on a switched-off network', function () {
    Queue::fake();
    reviewAccessPlatformsOff();
    reviewAccessGrant($this->account);
    $post = Post::factory()->forAccount($this->channel)->scheduled()->create([
        'user_id' => $this->user->id,
    ]);

    (new PublishPost($post))->handle();

    Queue::assertPushed(PublishToSocialPlatform::class);
    expect($post->fresh()->publish_block_reason)->toBeNull();
});

test('a network the server is not installed for stays blocked with Review access', function () {
    config(['services.linkedin.client_secret' => null]);
    reviewAccessPlatformsOff();
    $account = reviewAccessGrant($this->account);
    startSocialConnect($this->workspace, Platform::LinkedIn);

    expect(Platform::LinkedIn->isAvailableFor($account))->toBeFalse();

    $this->actingAs($this->user)
        ->get(route('app.social.connect.show', Platform::LinkedIn))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->post(route('app.posts.store'), [
            'status' => 'scheduled',
            'content' => 'Review access refusal',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'destinations' => [[
                'social_account_id' => $this->channel->id,
                'content_type' => 'linkedin_post',
            ]],
        ])
        ->assertSessionHasErrors(['destinations.0.social_account_id' => __('rxdesk.availability.schedule_refused', ['network' => 'LinkedIn'])]);
});
