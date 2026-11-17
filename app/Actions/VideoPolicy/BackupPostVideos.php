<?php

declare(strict_types=1);

namespace App\Actions\VideoPolicy;

use App\Enums\Media\Type as MediaType;
use App\Enums\Post\Status as PostStatus;
use App\Enums\VideoRecord\Outcome;
use App\Exceptions\Media\GoogleTokenRejected;
use App\Exceptions\VideoPolicy\DriveBackupUnavailable;
use App\Jobs\VideoPolicy\ApplyVideoPolicy;
use App\Models\Media;
use App\Models\Post;
use App\Models\VideoRecord;
use App\Services\VideoPolicy\GoogleDriveBackup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The Backup policy for one published post. Posts created together
 * (`post_group_id`) hold copies of the same file, so each file goes to the
 * Owner's Drive once: whichever published post of the group runs first
 * uploads its copy into "The RX Desk/<workspace>/<year-month>" under the
 * file's lock, and every other post reuses that Drive file once it is
 * recorded. A video leaves the server only after its Drive copy is confirmed
 * (`DeletePostVideos`).
 */
class BackupPostVideos
{
    /**
     * How long one upload may hold its lock: longer than the job may run
     * (`ApplyVideoPolicy::TIMEOUT_SECONDS`), so a run killed mid-upload frees
     * the file soon after, and a live one never loses it. A run that ends,
     * failed or not, releases it at once.
     */
    public const int UPLOAD_LOCK_SECONDS = ApplyVideoPolicy::TIMEOUT_SECONDS + 300;

    /**
     * Returns false while another run holds the upload lock of one of the
     * post's files (another post of the group, or another run of this one,
     * is uploading it): the caller looks again later.
     */
    public static function execute(Post $post): bool
    {
        if ($post->status !== PostStatus::Published) {
            return true;
        }

        /** @var Collection<int, Media> $videos */
        $videos = $post->ownedMedia()->where('type', MediaType::Video)->get();

        return $videos
            ->map(fn (Media $video): bool => self::backUp($post, $video))
            ->every(fn (bool $done): bool => $done);
    }

    /**
     * Whether another post of the group already recorded a failed backup, so
     * the Owner was already told about this group.
     */
    public static function groupHasFailed(Post $post): bool
    {
        return VideoRecord::query()
            ->whereIn('post_id', $post->groupPostIds())
            ->where('post_id', '!=', $post->id)
            ->where('outcome', Outcome::BackupFailed)
            ->exists();
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
     * Backs up one video, or reuses the group's Drive copy of its file.
     * False while another run holds the file's upload lock.
     */
    private static function backUp(Post $post, Media $video): bool
    {
        $sourceMediaId = self::sourceMediaId($video);

        if (self::reuse($post, $video, $sourceMediaId)) {
            return true;
        }

        $lock = Cache::lock(self::lockName($post, $sourceMediaId), self::UPLOAD_LOCK_SECONDS);

        if (! $lock->get()) {
            return false;
        }

        try {
            if (self::reuse($post, $video, $sourceMediaId)) {
                return true;
            }

            $file = self::upload($post, $video);
            DeletePostVideos::afterBackup($post, $video, $sourceMediaId, (string) data_get($file, 'id'), data_get($file, 'link'));

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Removes the video when the group already has its Drive copy.
     */
    private static function reuse(Post $post, Media $video, string $sourceMediaId): bool
    {
        $backup = self::groupBackup($post, $sourceMediaId);

        if (blank($backup)) {
            return false;
        }

        DeletePostVideos::afterBackup($post, $video, $sourceMediaId, (string) $backup->drive_file_id, $backup->drive_link);

        return true;
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
            ->whereIn('post_id', $post->groupPostIds())
            ->where('outcome', Outcome::BackedUp)
            ->where('source_media_id', $sourceMediaId)
            ->whereNotNull('drive_file_id')
            ->oldest()
            ->first();
    }

    /**
     * @return array{id: string, link: ?string}
     */
    private static function upload(Post $post, Media $video): array
    {
        $account = $post->workspace->account;
        $connection = $account->driveBackupConnection();

        if (blank($connection) || $connection->needsReconnect()) {
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
        $handle = $post->display_username ?? $post->display_name;
        $channel = Str::slug("{$post->platform?->value} {$handle}");
        $extension = pathinfo($video->path, PATHINFO_EXTENSION);

        if ($extension === '') {
            return "{$date}_{$channel}_{$post->id}";
        }

        return "{$date}_{$channel}_{$post->id}.{$extension}";
    }
}
