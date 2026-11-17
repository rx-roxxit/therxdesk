<?php

declare(strict_types=1);

namespace App\Actions\VideoPolicy;

use App\Enums\VideoRecord\Outcome;
use App\Jobs\VideoPolicy\ApplyVideoPolicy;
use App\Models\Post;
use App\Models\VideoRecord;

/**
 * The Owner's Retry on a post whose backup failed: the failed records of the
 * post and of the posts created with it are cleared (they share one upload),
 * and the policy is queued again for each right away. The job reads the
 * Video policy when it runs, so a policy changed since the failure decides
 * what happens: Keep does nothing, Delete deletes, Backup uploads.
 */
class RetryVideoBackup
{
    /**
     * Returns false, and queues nothing, when neither the post nor its group
     * has a failed backup to retry.
     */
    public static function execute(Post $post): bool
    {
        $failed = VideoRecord::query()
            ->whereIn('post_id', $post->groupPostIds())
            ->where('outcome', Outcome::BackupFailed);

        $failedPostIds = $failed->clone()->pluck('post_id');

        if ($failedPostIds->isEmpty()) {
            return false;
        }

        $failed->delete();

        $failedPostIds->push($post->id)->unique()->each(fn (string $postId) => ApplyVideoPolicy::dispatch($postId));

        return true;
    }
}
