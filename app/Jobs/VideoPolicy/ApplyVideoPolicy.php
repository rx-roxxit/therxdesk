<?php

declare(strict_types=1);

namespace App\Jobs\VideoPolicy;

use App\Actions\VideoPolicy\DeletePostVideos;
use App\Enums\Account\VideoPolicy;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applies the Owner's Video policy to a post's videos, an hour after it
 * published (long enough for every network to have fetched the file). It
 * re-reads the policy, and the action re-reads the post under its lock, so a
 * policy switched to Keep meanwhile, or a post no longer published, leaves the
 * videos alone.
 */
class ApplyVideoPolicy implements ShouldQueue
{
    use Queueable;

    public const int DELAY_MINUTES = 60;

    public function __construct(public string $postId) {}

    public function handle(): void
    {
        $post = Post::query()->find($this->postId);

        if ($post === null) {
            return;
        }

        $policy = $post->workspace?->account?->video_policy;

        if ($policy === null) {
            return;
        }

        match ($policy) {
            VideoPolicy::Delete => DeletePostVideos::execute($post),
            VideoPolicy::Keep, VideoPolicy::Backup => null,
        };
    }
}
