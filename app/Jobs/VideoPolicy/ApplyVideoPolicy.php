<?php

declare(strict_types=1);

namespace App\Jobs\VideoPolicy;

use App\Actions\VideoPolicy\BackupPostVideos;
use App\Actions\VideoPolicy\DeletePostVideos;
use App\Enums\Account\VideoPolicy;
use App\Enums\Availability\ServiceSwitch;
use App\Exceptions\VideoPolicy\DriveBackupUnavailable;
use App\Mail\VideoBackupFailed;
use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Applies the Owner's Video policy to a post's videos, an hour after it
 * published (long enough for every network to have fetched the file). It
 * re-reads the policy, and the action re-reads the post under its lock, so a
 * policy switched to Keep meanwhile, or a post no longer published, leaves the
 * videos alone.
 *
 * A large video can take hours to reach Drive, so the job runs on its own
 * queue (`QUEUE`): with Redis, on its own connection (`CONNECTION`, whose
 * `retry_after` outlasts the job) and Horizon supervisor (whose timeout
 * outlasts `TIMEOUT_SECONDS`), so a long upload is never cut short by the
 * default worker nor reserved twice.
 *
 * A Backup that fails (Google unreachable, a refused upload) is tried again on
 * the backoff below, about an hour in all, keeping the file meanwhile. After
 * the last try the post records `backup_failed` and the Owner is emailed once
 * per group. While the System admin has Drive backup switched off, a Backup
 * records `backup_failed` with "backup unavailable" at once, keeps the file and
 * sends no email; Retry works once the switch is back on. A rejected Google
 * token fails at once (retrying cannot help). A post whose file another run is
 * uploading (the file's lock is held) looks
 * again every `WAIT_SECONDS` in a fresh job that carries the tries already
 * used, so waiting never uses one up.
 */
class ApplyVideoPolicy implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'video-policy';

    /**
     * The Redis connection of that queue (`config/queue.php`).
     */
    public const string CONNECTION = 'video-policy';

    public const int DELAY_MINUTES = 60;

    /**
     * Seconds before each retry.
     *
     * @var list<int>
     */
    public const array BACKOFF_SECONDS = [300, 600, 900, 900, 900];

    /**
     * Seconds between two looks at a file another run is uploading.
     */
    public const int WAIT_SECONDS = 300;

    /**
     * How long one run may take (a whole upload restarts from its first
     * byte), under the `video-policy` supervisor's timeout.
     */
    public const int TIMEOUT_SECONDS = 10_500;

    public int $tries = 6;

    public int $timeout = self::TIMEOUT_SECONDS;

    /**
     * @param  int  $earlierAttempts  tries used by the earlier jobs of this post's wait
     */
    public function __construct(public string $postId, public int $earlierAttempts = 0)
    {
        $this->onQueue(self::QUEUE);
        $this->onConnection(self::connectionName());
    }

    /**
     * The long-running connection when the app queues on Redis, the default
     * connection otherwise (sync or database, which have no Horizon).
     */
    public static function connectionName(): ?string
    {
        if (config('queue.default') !== 'redis') {
            return null;
        }

        return self::CONNECTION;
    }

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

        if (blank($post)) {
            return;
        }

        $policy = $post->workspace?->account?->video_policy;

        if (blank($policy)) {
            return;
        }

        if ($policy === VideoPolicy::Backup && ! ServiceSwitch::DriveBackup->switchedOn()) {
            BackupPostVideos::fail($post, BackupPostVideos::UNAVAILABLE_REASON, $this->attemptsMade());

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

        $this->waitForUpload();
    }

    /**
     * Another run is uploading one of the post's files: look again later, in
     * a fresh job, without using up this try.
     */
    private function waitForUpload(): void
    {
        self::dispatch($this->postId, $this->attemptsMade() - 1)->delay(now()->addSeconds(self::WAIT_SECONDS));
    }

    /**
     * Records the failure, keeps the files, and tells the Owner once for the
     * whole group.
     */
    private function giveUp(Post $post, Throwable $exception, bool $needsReconnect = false): void
    {
        report($exception);

        $alreadyTold = BackupPostVideos::groupHasFailed($post);

        if (BackupPostVideos::fail($post, $exception->getMessage(), $this->attemptsMade()) === 0) {
            return;
        }

        if ($alreadyTold) {
            return;
        }

        Mail::to($post->workspace->account->owner)->send(new VideoBackupFailed($post, $needsReconnect));
    }

    /**
     * The tries used so far, this one included, across the jobs of a wait.
     */
    private function attemptsMade(): int
    {
        return $this->earlierAttempts + $this->attempts();
    }

    private function isLastAttempt(): bool
    {
        return $this->attemptsMade() >= $this->tries;
    }

    private function backoffSeconds(): int
    {
        return self::BACKOFF_SECONDS[min($this->attemptsMade(), count(self::BACKOFF_SECONDS)) - 1];
    }
}
