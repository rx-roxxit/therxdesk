<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Post\PublishStatus;
use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The in-app "Try again" for a post the publishing gate blocked. It does not
 * check the gate itself: PublishPost runs it again, so a retry while the
 * network is still off, or the Account is still Lapsed or Suspended, blocks
 * again instead of publishing.
 */
class RetryBlockedPost
{
    public static function execute(Post $post): void
    {
        DB::transaction(function () use ($post): void {
            $locked = Post::query()
                ->whereKey($post->id)
                ->lockForUpdate()
                ->first();

            $isBlocked = $locked !== null
                && $locked->status === PostStatus::Failed
                && $locked->publish_block_reason !== null;

            if (! $isBlocked) {
                throw ValidationException::withMessages([
                    'post' => __('rxdesk.publishing_block.retry_failed'),
                ]);
            }

            $locked->writePublication([
                'publish_status' => PublishStatus::Pending,
                'publish_block_reason' => null,
                'error_message' => null,
                'error_context' => null,
                'platform_post_id' => null,
                'platform_url' => null,
                'published_at' => null,
                'retry_at' => null,
            ]);
            $locked->update(['status' => PostStatus::Publishing]);
        });

        PublishPost::dispatch($post);
    }
}
