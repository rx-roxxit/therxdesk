<?php

declare(strict_types=1);

use App\Dto\MediaItem;
use App\Enums\Post\RecurrenceFrequency;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Jobs\PublishPost;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

beforeEach(function () {
    $this->freezeTime();
    Storage::fake();

    $this->channel = SocialAccount::factory()->instagram()->create([
        'platform_user_id' => 'ig_cover',
        'token_expires_at' => now()->addDays(60),
        'timezone' => 'UTC',
    ]);
    $this->graph = Platform::Instagram->graphApiUrl();

    Http::fake([
        "{$this->graph}/ig_cover/media" => Http::response(['id' => 'container-1']),
        "{$this->graph}/container-1*" => Http::response(['status_code' => 'FINISHED']),
        "{$this->graph}/ig_cover/media_publish" => Http::response(['id' => 'reel-1']),
        "{$this->graph}/reel-1*" => Http::response(['permalink' => 'https://www.instagram.com/reel/COVER/']),
    ]);
});

/**
 * A weekly Instagram Reel due now whose video has a Cover image of `$mimeType`.
 *
 * @return array{post: Post, video: Media, cover: Media}
 */
function instagramReelWithCover(SocialAccount $channel, string $mimeType): array
{
    $post = Post::factory()->forAccount($channel, ContentType::InstagramReel)->scheduled()->create([
        'user_id' => $channel->workspace->user_id,
        'scheduled_at' => now()->subMinute(),
        'content' => 'Weekly reel',
        'recurrence_frequency' => RecurrenceFrequency::Week,
        'recurrence_interval' => 1,
        'recurrence_remaining' => 3,
    ]);
    $video = Media::factory()->ownedByPost($post)->stored()->video()->create([
        'meta' => ['width' => 1080, 'height' => 1920, 'duration' => 15],
        'order' => 0,
    ]);
    $png = $mimeType === 'image/png';
    $cover = Media::factory()->ownedByPost($post)->create([
        'path' => 'media/'.Str::uuid().($png ? '.png' : '.jpg'),
        'mime_type' => $mimeType,
        'original_filename' => $png ? 'cover.png' : 'cover.jpg',
        'order' => 1,
    ]);
    Storage::put($cover->path, coverImageBytes($mimeType));

    $item = MediaItem::fromMedia($video)->toArray();
    $item['meta'] = [...$item['meta'], 'cover_offset_ms' => 1500, 'cover_image' => MediaItem::fromMedia($cover)->toArray()];
    $post->update(['media' => [$item]]);

    return ['post' => $post->fresh(), 'video' => $video, 'cover' => $cover];
}

function coverImageBytes(string $mimeType): string
{
    $image = (new ImageManager(Driver::class))->createImage(90, 160)->fill('336699');

    return (string) $image->encodeUsingMediaType($mimeType);
}

/**
 * The Reel container request Instagram received.
 */
function sentReelContainer(string $graph): Request
{
    $sent = Http::recorded(fn (Request $request): bool => $request->url() === "{$graph}/ig_cover/media");

    expect($sent)->toHaveCount(1);

    return $sent->first()[0];
}

test('a Reel with a JPEG Cover image sends it as cover_url instead of the picked frame', function () {
    ['post' => $post, 'cover' => $cover] = instagramReelWithCover($this->channel, 'image/jpeg');

    PublishPost::dispatchSync($post);

    $container = sentReelContainer($this->graph);

    expect($post->fresh()->status)->toBe(PostStatus::Published)
        ->and(data_get($container->data(), 'media_type'))->toBe('REELS')
        ->and(data_get($container->data(), 'cover_url'))->toBe($cover->url)
        ->and(data_get($container->data(), 'thumb_offset'))->toBeNull();
});

test('a PNG Cover image reaches Instagram as a JPEG', function () {
    ['post' => $post, 'cover' => $cover] = instagramReelWithCover($this->channel, 'image/png');

    PublishPost::dispatchSync($post);

    $coverUrl = (string) data_get(sentReelContainer($this->graph)->data(), 'cover_url');
    $converted = collect(Storage::allFiles())->first(fn (string $path): bool => $path !== $cover->path && str_ends_with($coverUrl, $path));

    expect($post->fresh()->status)->toBe(PostStatus::Published)
        ->and($converted)->not->toBeNull()
        ->and(getimagesizefromstring((string) Storage::get($converted))[2] ?? null)->toBe(IMAGETYPE_JPEG)
        ->and(Storage::exists($cover->path))->toBeTrue();
});

test('the next occurrence of a recurring Reel carries its own copy of the same Cover image', function () {
    ['post' => $post, 'cover' => $cover] = instagramReelWithCover($this->channel, 'image/jpeg');

    PublishPost::dispatchSync($post);

    $next = Post::query()->whereKeyNot($post->id)->sole();
    $nextCover = MediaItem::fromArray($next->media[0])->coverImage();
    $nextCoverRow = Media::query()->find($nextCover?->id);

    expect($nextCover)->not->toBeNull()
        ->and($nextCover->id)->not->toBe($cover->id)
        ->and($nextCoverRow?->post_id)->toBe($next->id)
        ->and(data_get($nextCoverRow?->meta, 'copied_from'))->toBe($cover->id)
        ->and(Storage::get($nextCoverRow->path))->toBe(Storage::get($cover->path))
        ->and($cover->fresh()->post_id)->toBe($post->id);
});
