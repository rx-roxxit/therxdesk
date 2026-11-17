<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    Queue::fake();

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, membershipPivot('admin'));
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->instagram = SocialAccount::factory()->instagram()->create(['workspace_id' => $this->workspace->id]);
    $this->post = Post::factory()->forAccount($this->instagram, ContentType::InstagramReel)->create([
        'user_id' => $this->user->id,
        'status' => Status::Draft,
        'scheduled_at' => null,
    ]);
    $this->video = Media::factory()->video()->ownedByPost($this->post)->stored()->create(['meta' => ['duration' => 10]]);
    $this->post->update(['media' => [MediaItem::fromMedia($this->video)->toArray()]]);
});

/**
 * A stored temporary upload the composer would send as a Cover image.
 *
 * @param  array<string, mixed>  $attributes
 */
function coverUpload(Workspace $workspace, array $attributes = []): Media
{
    return Media::factory()->temporaryUpload($workspace)->stored()->create([
        'type' => 'image',
        'mime_type' => 'image/jpeg',
        'original_filename' => 'cover.jpg',
        'size' => 500_000,
        ...$attributes,
    ]);
}

/**
 * The video's item as the composer sends it, with per-item `$meta` edits.
 *
 * @param  array<string, mixed>  $meta
 * @return array<string, mixed>
 */
function coverVideoItem(Media $video, array $meta = []): array
{
    $item = MediaItem::fromMedia($video)->toArray();
    $item['meta'] = [...(array) data_get($item, 'meta', []), ...$meta];

    return $item;
}

/**
 * @param  array<string, mixed>  $meta
 */
function saveCoverDraft(object $test, Post $post, Media $video, array $meta): Illuminate\Testing\TestResponse
{
    return $test->actingAs($test->user)->put(route('app.posts.update', $post), [
        'status' => 'draft',
        'content' => 'hi',
        'media' => [coverVideoItem($video, $meta)],
    ]);
}

test('the web composer stores a Cover image on the video, owned by the post', function () {
    $cover = coverUpload($this->workspace);

    saveCoverDraft($this, $this->post, $this->video, ['cover_image' => MediaItem::fromMedia($cover)->toArray()])
        ->assertSessionHasNoErrors();

    $stored = data_get($this->post->fresh()->media, '0.meta.cover_image');

    expect(data_get($stored, 'id'))->toBe($cover->id)
        ->and(data_get($stored, 'url'))->toBe($cover->fresh()->url)
        ->and($cover->fresh()->post_id)->toBe($this->post->id)
        ->and($cover->fresh()->collection)->toBe(Media::COLLECTION_MEDIA)
        ->and(MediaItem::fromArray($this->post->fresh()->media[0])->coverImage()?->id)->toBe($cover->id);
});

test('uploading a Cover image clears a picked frame', function () {
    saveCoverDraft($this, $this->post, $this->video, ['cover_offset_ms' => 1500])->assertSessionHasNoErrors();
    $cover = coverUpload($this->workspace);

    saveCoverDraft($this, $this->post, $this->video, [
        'cover_offset_ms' => 1500,
        'cover_image' => MediaItem::fromMedia($cover)->toArray(),
    ])->assertSessionHasNoErrors();

    $item = MediaItem::fromArray($this->post->fresh()->media[0]);

    expect($item->coverImage()?->id)->toBe($cover->id)
        ->and($item->coverOffsetMs())->toBeNull();
});

test('picking a frame drops the Cover image and deletes its row', function () {
    $cover = coverUpload($this->workspace);
    saveCoverDraft($this, $this->post, $this->video, ['cover_image' => MediaItem::fromMedia($cover)->toArray()])->assertSessionHasNoErrors();

    saveCoverDraft($this, $this->post, $this->video, ['cover_offset_ms' => 1500])->assertSessionHasNoErrors();

    $item = MediaItem::fromArray($this->post->fresh()->media[0]);

    expect($item->coverImage())->toBeNull()
        ->and($item->coverOffsetMs())->toBe(1500)
        ->and(Media::query()->find($cover->id))->toBeNull();
});

test('a stored Cover image stays when the post is saved again unchanged', function () {
    $cover = coverUpload($this->workspace);
    saveCoverDraft($this, $this->post, $this->video, ['cover_image' => MediaItem::fromMedia($cover)->toArray()])->assertSessionHasNoErrors();

    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => 'draft',
        'content' => 'hi again',
        'media' => $this->post->fresh()->media,
    ])->assertSessionHasNoErrors();

    expect(MediaItem::fromArray($this->post->fresh()->media[0])->coverImage()?->id)->toBe($cover->id)
        ->and($cover->fresh()->post_id)->toBe($this->post->id);
});

test('removing or swapping the video drops its Cover image', function (bool $swap) {
    $cover = coverUpload($this->workspace);
    saveCoverDraft($this, $this->post, $this->video, ['cover_image' => MediaItem::fromMedia($cover)->toArray()])->assertSessionHasNoErrors();
    $other = Media::factory()->video()->temporaryUpload($this->workspace)->stored()->create(['meta' => ['duration' => 8]]);

    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => 'draft',
        'content' => 'hi',
        'media' => $swap ? [MediaItem::fromMedia($other)->toArray()] : [],
    ])->assertSessionHasNoErrors();

    expect(Media::query()->find($cover->id))->toBeNull()
        ->and(data_get($this->post->fresh()->media, '0.meta.cover_image'))->toBeNull();
})->with(['removed' => [false], 'swapped' => [true]]);

test('a Cover image that is not a JPEG or PNG is refused', function (array $attributes) {
    $cover = coverUpload($this->workspace, $attributes);

    saveCoverDraft($this, $this->post, $this->video, ['cover_image' => MediaItem::fromMedia($cover)->toArray()])
        ->assertSessionHasErrors(['media.0.meta.cover_image' => 'The Cover image must be a JPEG or PNG. Export it in one of those formats, then upload it again.']);

    expect(data_get($this->post->fresh()->media, '0.meta.cover_image'))->toBeNull();
})->with([
    'a GIF' => [['mime_type' => 'image/gif', 'original_filename' => 'cover.gif']],
    'a WebP' => [['mime_type' => 'image/webp', 'original_filename' => 'cover.webp']],
    'a video' => [['type' => 'video', 'mime_type' => 'video/mp4', 'original_filename' => 'cover.mp4']],
]);

test('a Cover image over 8 MB is refused, and one of exactly 8 MB is stored', function (int $size, bool $refused) {
    $cover = coverUpload($this->workspace, ['mime_type' => 'image/png', 'size' => $size]);

    $response = saveCoverDraft($this, $this->post, $this->video, ['cover_image' => MediaItem::fromMedia($cover)->toArray()]);

    if ($refused) {
        $response->assertSessionHasErrors(['media.0.meta.cover_image' => 'The Cover image must be 8 MB or smaller. Export a smaller file, then upload it again.']);

        return;
    }

    $response->assertSessionHasNoErrors();
})->with([
    'over' => [8 * 1024 * 1024 + 1, true],
    'exactly' => [8 * 1024 * 1024, false],
]);

test('a Cover image is refused where the post cannot take one', function (ContentType $contentType, bool $carousel, bool $onImage) {
    $this->post->forceFill(['content_type' => $contentType])->save();
    $image = Media::factory()->ownedByPost($this->post)->stored()->create();
    $cover = coverUpload($this->workspace);
    $coverMeta = ['cover_image' => MediaItem::fromMedia($cover)->toArray()];
    $media = match (true) {
        $onImage => [coverVideoItem($image, $coverMeta)],
        $carousel => [coverVideoItem($this->video, $coverMeta), MediaItem::fromMedia($image)->toArray()],
        default => [coverVideoItem($this->video, $coverMeta)],
    };

    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => 'draft',
        'content' => 'hi',
        'content_type' => $contentType->value,
        'media' => $media,
    ])->assertSessionHasErrors(['media.0.meta.cover_image' => "This post type can't use a Cover image. Remove the Cover image, then try again."]);

    expect(Media::query()->find($cover->id)?->post_id)->toBeNull();
})->with([
    'an Instagram story' => [ContentType::InstagramStory, false, false],
    'an Instagram carousel' => [ContentType::InstagramFeed, true, false],
    'an image' => [ContentType::InstagramFeed, false, true],
]);

test('an Instagram feed post with a single video takes a Cover image', function () {
    $this->post->forceFill(['content_type' => ContentType::InstagramFeed])->save();
    $cover = coverUpload($this->workspace);

    $this->actingAs($this->user)->put(route('app.posts.update', $this->post), [
        'status' => 'draft',
        'content' => 'hi',
        'content_type' => ContentType::InstagramFeed->value,
        'media' => [coverVideoItem($this->video, ['cover_image' => MediaItem::fromMedia($cover)->toArray()])],
    ])->assertSessionHasNoErrors();

    expect(MediaItem::fromArray($this->post->fresh()->media[0])->coverImage()?->id)->toBe($cover->id);
});

test('a Cover image of another workspace is refused', function () {
    $cover = coverUpload(Workspace::factory()->create());

    saveCoverDraft($this, $this->post, $this->video, ['cover_image' => MediaItem::fromMedia($cover)->toArray()])
        ->assertSessionHasErrors(['media.0.meta.cover_image' => "The Cover image couldn't be found. Upload it again."]);

    expect($cover->fresh()->post_id)->toBeNull();
});

test('a Cover image that is not a media item is refused', function () {
    saveCoverDraft($this, $this->post, $this->video, ['cover_image' => 'https://example.com/cover.jpg'])
        ->assertSessionHasErrors(['media.0.meta' => "The Cover image couldn't be found. Upload it again."]);

    expect(data_get($this->post->fresh()->media, '0.meta.cover_image'))->toBeNull();
});

test('a member whose posts need approval sends a Cover image for approval, and approving keeps it', function () {
    $requester = workspaceMember($this->workspace, 'approval');
    $cover = coverUpload($this->workspace);
    $this->post->forceFill(['user_id' => $requester->id])->save();

    $this->actingAs($requester)->put(route('app.posts.update', $this->post), [
        'status' => 'scheduled',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'content' => 'For approval',
        'media' => [coverVideoItem($this->video, ['cover_image' => MediaItem::fromMedia($cover)->toArray()])],
    ])->assertSessionHasNoErrors();

    expect($this->post->fresh()->status)->toBe(Status::PendingApproval)
        ->and(MediaItem::fromArray($this->post->fresh()->media[0])->coverImage()?->id)->toBe($cover->id);

    $this->actingAs($this->user)->put(route('app.posts.approve', $this->post))->assertSessionHasNoErrors();

    expect($this->post->fresh()->status)->toBe(Status::Scheduled)
        ->and(MediaItem::fromArray($this->post->fresh()->media[0])->coverImage()?->id)->toBe($cover->id)
        ->and($cover->fresh()->post_id)->toBe($this->post->id);
});
