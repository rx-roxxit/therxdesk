<?php

declare(strict_types=1);

namespace App\Actions\VideoPolicy;

use App\Actions\Media\SyncOwnedMedia;
use App\Enums\Media\Type as MediaType;
use App\Enums\Post\Status as PostStatus;
use App\Enums\VideoRecord\Outcome;
use App\Models\Media;
use App\Models\Post;
use App\Models\VideoRecord;
use App\Support\Media\MediaCopyBatch;
use App\Support\ThreadReplies;
use Illuminate\Database\Eloquent\Collection;

/**
 * Removes a published post's videos (its own and its thread replies') and
 * records each one as deleted. The rows go through upstream's owned-media
 * deletion (`SyncOwnedMedia` releases them to `DeleteOwnedMedia`, which deletes
 * the files after commit), so `posts.media` stops listing them. Images and
 * documents stay.
 */
class DeletePostVideos
{
    public static function execute(Post $post): void
    {
        MediaCopyBatch::run(function (MediaCopyBatch $batch) use ($post): void {
            $locked = Post::query()->whereKey($post->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== PostStatus::Published) {
                return;
            }

            /** @var Collection<int, Media> $videos */
            $videos = $locked->ownedMedia()->where('type', MediaType::Video)->get();

            if ($videos->isEmpty()) {
                return;
            }

            $videoIds = $videos->modelKeys();
            $replies = ThreadReplies::of($locked->meta);

            if ($replies !== []) {
                $locked->update(['meta' => [
                    ...$locked->meta,
                    'thread_replies' => array_map(fn (array $reply): array => [
                        ...$reply,
                        'media' => self::without($reply['media'], $videoIds),
                    ], $replies),
                ]]);
            }

            SyncOwnedMedia::execute($locked, self::without($locked->media ?? [], $videoIds), $batch);

            foreach ($videos as $video) {
                VideoRecord::query()->create([
                    'post_id' => $locked->id,
                    'media_id' => $video->id,
                    'original_filename' => $video->original_filename,
                    'size' => $video->size,
                    'outcome' => Outcome::Deleted,
                ]);
            }
        });
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  list<string>  $videoIds
     * @return list<array<string, mixed>>
     */
    private static function without(array $items, array $videoIds): array
    {
        return array_values(array_filter(
            $items,
            fn (mixed $item): bool => is_array($item) && ! in_array(data_get($item, 'id'), $videoIds, true),
        ));
    }
}
