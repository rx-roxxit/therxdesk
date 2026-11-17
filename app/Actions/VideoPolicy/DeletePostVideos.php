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
 * records what became of each one. The rows go through upstream's owned-media
 * deletion (`SyncOwnedMedia` releases them to `DeleteOwnedMedia`, which deletes
 * the files after commit), so `posts.media` stops listing them. Images and
 * documents stay.
 */
class DeletePostVideos
{
    /**
     * The Delete policy: every video goes, recorded as deleted.
     */
    public static function execute(Post $post): void
    {
        self::remove($post, null, ['outcome' => Outcome::Deleted]);
    }

    /**
     * The Backup policy, once Google confirmed the Drive copy of one video:
     * that video goes, recorded as backed up with its Drive file.
     */
    public static function afterBackup(Post $post, Media $video, string $sourceMediaId, string $driveFileId, ?string $driveLink): void
    {
        self::remove($post, [$video->id], [
            'outcome' => Outcome::BackedUp,
            'source_media_id' => $sourceMediaId,
            'drive_file_id' => $driveFileId,
            'drive_link' => $driveLink,
        ]);
    }

    /**
     * Re-reads the post under its lock and acts only while it is still
     * published.
     *
     * @param  list<string>|null  $videoIds  the videos to remove; null for all of them
     * @param  array<string, mixed>  $record  what each video's record says
     */
    private static function remove(Post $post, ?array $videoIds, array $record): void
    {
        MediaCopyBatch::run(function (MediaCopyBatch $batch) use ($post, $videoIds, $record): void {
            $locked = Post::query()->whereKey($post->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== PostStatus::Published) {
                return;
            }

            $query = $locked->ownedMedia()->where('type', MediaType::Video);

            if ($videoIds !== null) {
                $query->whereKey($videoIds);
            }

            /** @var Collection<int, Media> $videos */
            $videos = $query->get();

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
                    ...$record,
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
