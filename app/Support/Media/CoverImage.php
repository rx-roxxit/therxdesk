<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Actions\Media\ResolveWorkspaceMedia;
use App\Actions\Media\SyncOwnedMedia;
use App\Dto\MediaItem;
use App\Enums\Media\Type as MediaType;
use App\Enums\PostPlatform\ContentType;
use App\Models\Media;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The one home of a video's Cover image rules: which posts take one, and which
 * files may be one. Every save (web, API, MCP) checks them through
 * `PostCompositionValidator`; the composer reads the same numbers from the
 * content type's media rules.
 */
class CoverImage
{
    /**
     * Instagram's `cover_url` limit, the lowest of the networks that take a Cover.
     */
    public const int MAX_BYTES = 8 * 1024 * 1024;

    /**
     * @var list<string>
     */
    public const array MIME_TYPES = ['image/jpeg', 'image/png'];

    /**
     * A Cover image goes on the single video of a post whose content type
     * takes one: a carousel, a story or an image never does.
     *
     * @param  array<int, mixed>  $media
     */
    public static function fitsPost(?ContentType $contentType, array $media, bool $itemIsVideo): bool
    {
        if ($contentType === null || ! $contentType->supportsCoverImage()) {
            return false;
        }

        return count($media) === 1 && $itemIsVideo;
    }

    /**
     * The Cover image row of a post's video, when it has one.
     */
    public static function forPost(Post $post): ?Media
    {
        $item = collect($post->media)->first(fn (mixed $item): bool => is_array($item));
        $id = transform($item, fn (array $item): ?string => MediaItem::fromArray($item)->coverImage()?->id);

        if (blank($id)) {
            return null;
        }

        return Media::query()->where('post_id', $post->id)->whereKey($id)->first();
    }

    /**
     * Errors keyed by the item's path under the media list
     * (`{index}.meta.cover_image`).
     *
     * @param  array<int, mixed>  $media
     * @return array<string, string>
     */
    public static function violations(Workspace $workspace, ?ContentType $contentType, array $media): array
    {
        $covers = collect($media)
            ->filter(fn (mixed $item): bool => is_array(data_get($item, 'meta.'.SyncOwnedMedia::COVER_IMAGE)));

        if ($covers->isEmpty()) {
            return [];
        }

        $rows = self::rows($workspace, [...$media, ...$covers->map(fn (array $item): array => data_get($item, 'meta.'.SyncOwnedMedia::COVER_IMAGE))->all()]);

        return $covers
            ->map(function (array $item) use ($contentType, $media, $rows): ?string {
                $video = self::row($rows, $item);
                $isVideo = $video === null ? MediaItem::fromArray($item)->isVideo() : $video->type === MediaType::Video;

                if (! self::fitsPost($contentType, $media, $isVideo)) {
                    return __('rxdesk.cover.errors.not_supported');
                }

                return self::fileViolation(self::row($rows, data_get($item, 'meta.'.SyncOwnedMedia::COVER_IMAGE)));
            })
            ->filter()
            ->mapWithKeys(fn (string $message, int $index): array => ["{$index}.meta.".SyncOwnedMedia::COVER_IMAGE => $message])
            ->all();
    }

    /**
     * Why this file cannot be a Cover image, or null when it can.
     */
    public static function fileViolation(?Media $file): ?string
    {
        if ($file === null) {
            return __('rxdesk.cover.errors.not_found');
        }

        if ($file->type !== MediaType::Image || ! in_array($file->mime_type, self::MIME_TYPES, true)) {
            return __('rxdesk.cover.errors.type');
        }

        if ($file->size > self::MAX_BYTES) {
            return __('rxdesk.cover.errors.too_large', ['max' => self::MAX_BYTES / 1024 / 1024]);
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array{ids: Collection<string, Media>, tokens: Collection<string, Media>}
     */
    private static function rows(Workspace $workspace, array $items): array
    {
        $values = fn (string $key): array => collect($items)
            ->map(fn (mixed $item): mixed => data_get($item, $key))
            ->filter(fn (mixed $value): bool => is_string($value) && Str::isUuid($value))
            ->values()
            ->all();

        return [
            'ids' => ResolveWorkspaceMedia::execute($workspace, $values('id')),
            'tokens' => ResolveWorkspaceMedia::byUploadTokens($workspace, $values('upload_token')),
        ];
    }

    /**
     * @param  array{ids: Collection<string, Media>, tokens: Collection<string, Media>}  $rows
     */
    private static function row(array $rows, mixed $item): ?Media
    {
        $token = data_get($item, 'upload_token');

        if (is_string($token) && filled($token)) {
            return $rows['tokens']->get($token);
        }

        $id = data_get($item, 'id');

        return is_string($id) ? $rows['ids']->get($id) : null;
    }
}
