<?php

declare(strict_types=1);

namespace App\Jobs\VideoPolicy;

use App\Actions\VideoPolicy\BackupPostVideos;
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
 *
 * A Backup that fails (Google unreachable, a refused upload) is tried again on
 * the backoff below, about an hour in all, keeping the file meanwhile. A post
 * whose group upload is not done yet waits on the same schedule.
 */
class ApplyVideoPolicy implements ShouldQueue
{
    use Queueable;

    public const int DELAY_MINUTES = 60;

    /**
     * Seconds before each retry.
     *
     * @var list<int>
     */
    public const array BACKOFF_SECONDS = [300, 600, 900, 900, 900];

    public int $tries = 6;

    public function __construct(public string $postId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return self::BACKOFF_SECONDS;
    }

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
            VideoPolicy::Backup => $this->backup($post),
            VideoPolicy::Keep => null,
        };
    }

    private function backup(Post $post): void
    {
        if (BackupPostVideos::execute($post)) {
            return;
        }

        $this->release(self::BACKOFF_SECONDS[min($this->attempts(), count(self::BACKOFF_SECONDS)) - 1]);
    }
}
