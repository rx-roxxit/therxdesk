<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\PostPlatform\ContentType;
use App\Jobs\Cover\SetPostCover;
use App\Jobs\VideoPolicy\ApplyVideoPolicy;
use App\Models\Account;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Storage\AccountStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeDiskSpace;

beforeEach(function () {
    config(['filesystems.default' => 'r2']);
    Storage::fake('r2', ['url' => 'https://media.therxdesk.test']);
    config(['rxdesk.storage.default_quota' => '10MB']);

    $this->mb = 1024 * 1024;
    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);
    $this->workspace = Workspace::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    subscribeAccount($this->account);

    $this->first = SocialAccount::factory()->instagram()->create(['workspace_id' => $this->workspace->id]);
    $this->second = SocialAccount::factory()->instagram()->create(['workspace_id' => $this->workspace->id]);
    $this->video = Media::factory()->video()->temporaryUpload($this->workspace)->stored()->create(['size' => 1024, 'meta' => ['duration' => 10]]);
    $this->cover = Media::factory()->temporaryUpload($this->workspace)->stored()->create(['mime_type' => 'image/png', 'size' => 4 * $this->mb]);
});

/**
 * One Reel with a Cover image posted to both Instagram channels: the first
 * post takes the uploads, the second gets its own copies.
 */
function postReelWithCoverToBothChannels(object $test): TestResponse
{
    $item = MediaItem::fromMedia($test->video)->toArray();
    $item['meta'] = [...$item['meta'], 'cover_image' => MediaItem::fromMedia($test->cover)->toArray()];
    $destination = fn (SocialAccount $channel): array => ['social_account_id' => $channel->id, 'content_type' => ContentType::InstagramReel->value, 'meta' => []];

    return $test->actingAs($test->owner)->from(route('app.posts.index'))->post(route('app.posts.store'), [
        'status' => 'draft',
        'content' => 'One Reel, two channels',
        'media' => [$item],
        'destinations' => [$destination($test->first), $destination($test->second)],
    ]);
}

test('each channel gets its own copy of the Cover image, and every copy counts in Storage used', function () {
    postReelWithCoverToBothChannels($this)->assertSessionHasNoErrors();

    $covers = Post::query()->get()->map(fn (Post $post): ?MediaItem => MediaItem::fromArray($post->media[0])->coverImage());

    expect($covers->filter())->toHaveCount(2)
        ->and($covers->pluck('id')->unique())->toHaveCount(2)
        ->and(app(AccountStorage::class)->usage($this->account)->used)->toBe(2 * (4 * $this->mb + 1024));
});

test('a copy of the Cover image over the Quota refuses the whole save', function () {
    Media::factory()->temporaryUpload($this->workspace)->create(['size' => 3 * $this->mb]);

    postReelWithCoverToBothChannels($this)
        ->assertSessionHasErrors(['media' => "You're out of storage: you've used 7 MB of your 10 MB. Delete posts or media you no longer need, then try again."]);

    expect(Post::query()->count())->toBe(0)
        ->and($this->cover->fresh()->collection)->toBe(Media::COLLECTION_UPLOADS);
});

test('a copy of the Cover image under the Disk guard refuses the whole save', function () {
    FakeDiskSpace::bind(free: 1024 * $this->mb);

    postReelWithCoverToBothChannels($this)->assertSessionHasErrors('media');

    expect(Post::query()->count())->toBe(0);
});

test('the Cover job gives up before the Video policy can delete the Cover image', function () {
    $coverWindow = SetPostCover::FIRST_DELAY_SECONDS + array_sum(SetPostCover::BACKOFF_SECONDS);

    expect($coverWindow)->toBeLessThan(ApplyVideoPolicy::DELAY_MINUTES * 60);
});
