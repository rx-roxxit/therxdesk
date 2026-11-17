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
    public static function execute(Post $post): void
    {
        $groupPostIds = self::groupPostIds($post);

        $failed = VideoRecord::query()
            ->whereIn('post_id', $groupPostIds)
            ->where('outcome', Outcome::BackupFailed);

        $postIds = [$post->id, ...$failed->clone()->pluck('post_id')->all()];

        $failed->delete();

        foreach (array_unique($postIds) as $postId) {
            ApplyVideoPolicy::dispatch($postId);
        }
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
}
