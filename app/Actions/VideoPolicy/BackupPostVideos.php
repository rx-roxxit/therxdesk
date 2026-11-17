<?php

declare(strict_types=1);

namespace App\Actions\VideoPolicy;

use App\Enums\Media\Type as MediaType;
use App\Enums\Post\Status as PostStatus;
use App\Enums\VideoRecord\Outcome;
use App\Models\Media;
use App\Models\Post;
use App\Models\VideoRecord;
use App\Services\VideoPolicy\GoogleDriveBackup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The Backup policy for one published post. Posts created together
 * (`post_group_id`) hold copies of the same file, so each file goes to the
 * Owner's Drive once: the first published post of the group holding it
 * uploads its copy into "The RX Desk/<workspace>/<year-month>", and every
 * other post reuses that Drive file once it is recorded. A video leaves the
 * server only after its Drive copy is confirmed (`DeletePostVideos`).
 */
class BackupPostVideos
{
    /**
     * Returns false while a video waits for its group's upload, which another
     * post of the group does: the caller tries again later.
     */
    public static function execute(Post $post): bool
    {
        if ($post->status !== PostStatus::Published) {
            return true;
        }

        /** @var Collection<int, Media> $videos */
        $videos = $post->ownedMedia()->where('type', MediaType::Video)->get();
        $waiting = false;

        foreach ($videos as $video) {
            $sourceMediaId = self::sourceMediaId($video);
            $backup = self::groupBackup($post, $sourceMediaId);

            if ($backup !== null) {
                DeletePostVideos::afterBackup($post, $video, $sourceMediaId, (string) $backup->drive_file_id, $backup->drive_link);

                continue;
            }

            if (! self::uploads($post, $sourceMediaId)) {
                $waiting = true;

                continue;
            }

            $file = self::upload($post, $video);
            DeletePostVideos::afterBackup($post, $video, $sourceMediaId, $file['id'], $file['link']);
        }

        return ! $waiting;
    }

    /**
     * The file a video was copied from when its post was created with others,
     * or the video itself when it is the original.
     */
    private static function sourceMediaId(Media $video): string
    {
        return (string) data_get($video->meta, 'copied_from', $video->id);
    }

    /**
     * The Drive copy a post of the group already made of this file.
     */
    private static function groupBackup(Post $post, string $sourceMediaId): ?VideoRecord
    {
        return VideoRecord::query()
            ->whereIn('post_id', self::groupPostIds($post))
            ->where('outcome', Outcome::BackedUp)
            ->where('source_media_id', $sourceMediaId)
            ->whereNotNull('drive_file_id')
            ->oldest()
            ->first();
    }

    /**
     * Whether this post is the one that uploads the file: the first published
     * post of its group still holding a copy of it.
     */
    private static function uploads(Post $post, string $sourceMediaId): bool
    {
        $uploader = Post::query()
            ->whereKey(self::groupPostIds($post))
            ->published()
            ->with(['ownedMedia' => fn (HasMany $query) => $query->where('type', MediaType::Video)])
            ->orderBy('published_at')
            ->orderBy('id')
            ->get()
            ->first(fn (Post $candidate): bool => $candidate->ownedMedia->contains(
                fn (Media $video): bool => self::sourceMediaId($video) === $sourceMediaId,
            ));

        return $uploader?->is($post) === true;
    }

    /**
     * @return list<string>
     */
    private static function groupPostIds(Post $post): array
    {
        if (blank($post->post_group_id)) {
            return [$post->id];
        }

        return Post::query()->where('post_group_id', $post->post_group_id)->pluck('id')->all();
    }

    /**
     * @return array{id: string, link: ?string}
     */
    private static function upload(Post $post, Media $video): array
    {
        $account = $post->workspace->account;
        $connection = $account->driveBackupConnection();

        if ($connection === null || $connection->needsReconnect()) {
            throw new RuntimeException("The account {$account->id} has no working Google Drive connection.");
        }

        $drive = app(GoogleDriveBackup::class);
        $accessToken = $drive->accessToken($connection);
        $publishedAt = $post->published_at->copy()->setTimezone($account->owner->timezone);
        $folderId = $drive->folder($accessToken, [
            __('rxdesk.drive_backup.folder'),
            $post->workspace->name,
            $publishedAt->format('Y-m'),
        ]);

        return $drive->upload($accessToken, $folderId, self::fileName($post, $video, $publishedAt->format('Y-m-d')), $video);
    }

    /**
     * `<date>_<channel>_<post id>.<ext>`, the channel as its network and
     * handle (`instagram-acme`).
     */
    private static function fileName(Post $post, Media $video, string $date): string
    {
        $channel = Str::slug("{$post->platform?->value} ".($post->display_username ?? $post->display_name));
        $extension = pathinfo($video->path, PATHINFO_EXTENSION);

        if ($extension === '') {
            return "{$date}_{$channel}_{$post->id}";
        }

        return "{$date}_{$channel}_{$post->id}.{$extension}";
    }
}
