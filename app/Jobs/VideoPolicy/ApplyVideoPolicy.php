<?php

declare(strict_types=1);

namespace App\Jobs\VideoPolicy;

use App\Actions\VideoPolicy\BackupPostVideos;
use App\Actions\VideoPolicy\DeletePostVideos;
use App\Enums\Account\VideoPolicy;
use App\Exceptions\VideoPolicy\DriveBackupUnavailable;
use App\Exceptions\VideoPolicy\GroupBackupFailed;
use App\Mail\VideoBackupFailed;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Applies the Owner's Video policy to a post's videos, an hour after it
 * published (long enough for every network to have fetched the file). It
 * re-reads the policy, and the action re-reads the post under its lock, so a
 * policy switched to Keep meanwhile, or a post no longer published, leaves the
 * videos alone.
 *
 * A Backup that fails (Google unreachable, a refused upload) is tried again on
 * the backoff below, about an hour in all, keeping the file meanwhile. After
 * the last try the post records `backup_failed` and the Owner is emailed. A
 * rejected Google token fails at once (retrying cannot help). A post whose
 * group upload is not done yet waits on the same schedule; when its tries run
 * out while the uploader has not given up, it queues itself again rather than
 * failing, up to `MAX_WAIT_ROUNDS` times.
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

    /**
     * How many more times a waiting post queues itself after running out of
     * tries (each round is another hour of waiting).
     */
    public const int MAX_WAIT_ROUNDS = 3;

    public int $tries = 6;

    public function __construct(public string $postId, public int $round = 0) {}

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
        try {
            if (BackupPostVideos::execute($post)) {
                return;
            }
        } catch (GroupBackupFailed $exception) {
            BackupPostVideos::fail($post, $exception->getMessage(), $this->attempts());

            return;
        } catch (DriveBackupUnavailable $exception) {
            $this->giveUp($post, $exception, needsReconnect: true);

            return;
        } catch (Throwable $exception) {
            if ($this->isLastAttempt()) {
                $this->giveUp($post, $exception);

                return;
            }

            $this->release($this->backoffSeconds());

            return;
        }

        $this->wait($post);
    }

    /**
     * The post waits on its group's upload.
     */
    private function wait(Post $post): void
    {
        if (! $this->isLastAttempt()) {
            $this->release($this->backoffSeconds());

            return;
        }

        if ($this->round < self::MAX_WAIT_ROUNDS) {
            self::dispatch($this->postId, $this->round + 1)->delay(now()->addSeconds($this->backoffSeconds()));

            return;
        }

        $this->giveUp($post, new RuntimeException('The group upload this post waited for did not finish.'));
    }

    /**
     * Records the failure, keeps the files, and tells the Owner once.
     */
    private function giveUp(Post $post, Throwable $exception, bool $needsReconnect = false): void
    {
        report($exception);

        if (BackupPostVideos::fail($post, $exception->getMessage(), $this->attempts()) === 0) {
            return;
        }

        $owner = $post->workspace->account->owner;

        Mail::to($owner)->send(new VideoBackupFailed($post, $needsReconnect));
    }

    private function isLastAttempt(): bool
    {
        return $this->attempts() >= $this->tries;
    }

    private function backoffSeconds(): int
    {
        return self::BACKOFF_SECONDS[min($this->attempts(), count(self::BACKOFF_SECONDS)) - 1];
    }
}
