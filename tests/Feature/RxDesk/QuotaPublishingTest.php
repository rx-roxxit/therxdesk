<?php

declare(strict_types=1);

use App\Enums\Post\PublishStatus;
use App\Enums\Post\RecurrenceFrequency;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Jobs\PublishToSocialPlatform;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Storage\AccountStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('over its Quota an account still publishes a scheduled post and its next occurrence, and nothing is deleted', function () {
    $this->freezeTime();
    Storage::fake();

    $channel = SocialAccount::factory()->create([
        'platform' => Platform::Facebook,
        'scopes' => Platform::Facebook->requiredPublishScopes(),
        'timezone' => 'UTC',
    ]);
    $post = Post::factory()->forAccount($channel, ContentType::defaultFor(Platform::Facebook))->scheduled()->create([
        'user_id' => $channel->workspace->user_id,
        'scheduled_at' => now()->subMinute(),
        'content' => 'Weekly photo',
        'recurrence_frequency' => RecurrenceFrequency::Week,
        'recurrence_interval' => 1,
        'recurrence_remaining' => 3,
    ]);
    $photo = Media::factory()->ownedByPost($post)->stored()->create(['size' => 100000, 'meta' => ['width' => 1080, 'height' => 1080]]);
    $post->update(['media' => [$photo->toArray()]]);

    $account = $channel->workspace->account;
    $account->forceFill(['quota_bytes' => 1000])->save();
    expect(app(AccountStorage::class)->canStore($account, 1))->toBeFalse();

    Http::fake([
        config('trypost.platforms.facebook.graph_api')."/{$channel->platform_user_id}/photos" => Http::response(['id' => 'photo-123']),
    ]);

    (new PublishToSocialPlatform($post->fresh()))->handle();

    $nextOccurrence = Post::query()->whereKeyNot($post->id)->sole();

    expect($post->refresh()->status)->toBe(PostStatus::Published)
        ->and($post->publish_status)->toBe(PublishStatus::Published)
        ->and($photo->fresh())->not->toBeNull()
        ->and(Storage::exists($photo->path))->toBeTrue()
        ->and($nextOccurrence->status)->toBe(PostStatus::Scheduled)
        ->and(Media::query()->where('post_id', $nextOccurrence->id)->count())->toBe(1);
});
