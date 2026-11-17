<?php

declare(strict_types=1);

namespace App\Actions\VideoPolicy;

use App\Enums\Media\Type as MediaType;
use App\Enums\Post\Status as PostStatus;
use App\Enums\VideoRecord\Outcome;
use App\Exceptions\Media\GoogleTokenRejected;
use App\Exceptions\VideoPolicy\DriveBackupUnavailable;
use App\Exceptions\VideoPolicy\GroupBackupFailed;
use App\Jobs\VideoPolicy\ApplyVideoPolicy;
use App\Models\Media;
use App\Models\Post;
use App\Models\VideoRecord;
use App\Services\VideoPolicy\GoogleDriveBackup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
     * How long one upload may hold its lock, so a run that dies mid-upload
     * does not block the file for longer than the job's own retries.
     */
    private const int UPLOAD_LOCK_SECONDS = 3600;

    /**
     * Returns false while a video waits for its group's upload (another post
     * of the group does it, or another run of this one is under way): the
     * caller tries again later. A waiting post whose uploader gave up throws
     * `GroupBackupFailed`.
     */
    public static function execute(Post $post): bool
    {
        if ($post->status !== PostStatus::Published) {
            return true;
        }

        /** @var Collection<int, Media> $videos */
        $videos = $post->ownedMedia()->where('type', MediaType::Video)->get();
        $waiting = false;
        $woken = [];

        foreach ($videos as $video) {
            $sourceMediaId = self::sourceMediaId($video);

            if (self::reuse($post, $video, $sourceMediaId)) {
                continue;
            }

            if (! self::uploads($post, $sourceMediaId)) {
                self::assertGroupNotFailed($post, $sourceMediaId);
                $waiting = true;

                continue;
            }

            $lock = Cache::lock(self::lockName($post, $sourceMediaId), self::UPLOAD_LOCK_SECONDS);

            if (! $lock->get()) {
                $waiting = true;

                continue;
            }

            try {
                if (self::reuse($post, $video, $sourceMediaId)) {
                    continue;
                }

                $file = self::upload($post, $video);
                DeletePostVideos::afterBackup($post, $video, $sourceMediaId, $file['id'], $file['link']);
                $woken = [...$woken, ...self::otherHolderIds($post, $sourceMediaId)];
            } finally {
                $lock->release();
            }
        }

        foreach (array_unique($woken) as $postId) {
            ApplyVideoPolicy::dispatch($postId);
        }

        return ! $waiting;
    }

    /**
     * Records that the backup gave up on this post's videos, keeping them on
     * the server, and returns how many records it wrote.
     */
    public static function fail(Post $post, string $error, int $attempts): int
    {
        if ($post->status !== PostStatus::Published) {
            return 0;
        }

        $written = 0;

        foreach ($post->ownedMedia()->where('type', MediaType::Video)->get() as $video) {
            $record = VideoRecord::query()->firstOrCreate(
                ['post_id' => $post->id, 'media_id' => $video->id, 'outcome' => Outcome::BackupFailed],
                [
                    'source_media_id' => self::sourceMediaId($video),
                    'original_filename' => $video->original_filename,
                    'size' => $video->size,
                    'attempts' => $attempts,
                    'last_error' => Str::limit($error, 500),
                ],
            );

            if ($record->wasRecentlyCreated) {
                $written++;
            }
        }

        return $written;
    }

    /**
     * Removes the video when the group already has its Drive copy.
     */
    private static function reuse(Post $post, Media $video, string $sourceMediaId): bool
    {
        $backup = self::groupBackup($post, $sourceMediaId);

        if ($backup === null) {
            return false;
        }

        DeletePostVideos::afterBackup($post, $video, $sourceMediaId, (string) $backup->drive_file_id, $backup->drive_link);

        return true;
    }

    /**
     * A post waiting on its group's upload stops waiting once the uploader
     * recorded that it gave up.
     */
    private static function assertGroupNotFailed(Post $post, string $sourceMediaId): void
    {
        $failure = VideoRecord::query()
            ->whereIn('post_id', self::groupPostIds($post))
            ->where('post_id', '!=', $post->id)
            ->where('outcome', Outcome::BackupFailed)
            ->where('source_media_id', $sourceMediaId)
            ->oldest()
            ->first();

        if ($failure !== null) {
            throw new GroupBackupFailed((string) $failure->last_error);
        }
    }

    private static function lockName(Post $post, string $sourceMediaId): string
    {
        $group = $post->post_group_id ?? $post->id;

        return "video-backup:{$group}:{$sourceMediaId}";
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
        return self::holders($post, $sourceMediaId)->first()?->is($post) === true;
    }

    /**
     * The ids of the other published posts of the group still holding a copy
     * of the file, which a finished upload lets finish at once.
     *
     * @return list<string>
     */
    private static function otherHolderIds(Post $post, string $sourceMediaId): array
    {
        return self::holders($post, $sourceMediaId)
            ->reject(fn (Post $holder): bool => $holder->is($post))
            ->pluck('id')
            ->all();
    }

    /**
     * The published posts of the group still holding a copy of the file,
     * the uploader first.
     *
     * @return SupportCollection<int, Post>
     */
    private static function holders(Post $post, string $sourceMediaId): SupportCollection
    {
        return Post::query()
            ->whereKey(self::groupPostIds($post))
            ->published()
            ->with(['ownedMedia' => fn (HasMany $query) => $query->where('type', MediaType::Video)])
            ->orderBy('published_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Post $candidate): bool => $candidate->ownedMedia->contains(
                fn (Media $video): bool => self::sourceMediaId($video) === $sourceMediaId,
            ))
            ->values();
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
            throw new DriveBackupUnavailable("The account {$account->id} has no working Google Drive connection.");
        }

        $drive = app(GoogleDriveBackup::class);

        try {
            $accessToken = $drive->accessToken($connection);
        } catch (GoogleTokenRejected $exception) {
            MarkDriveNeedsReconnect::execute($account);

            throw new DriveBackupUnavailable($exception->getMessage(), previous: $exception);
        }

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
