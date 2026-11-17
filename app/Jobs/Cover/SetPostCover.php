<?php

declare(strict_types=1);

namespace App\Jobs\Cover;

use App\Enums\Cover\Outcome;
use App\Enums\Post\Status as PostStatus;
use App\Exceptions\Cover\CoverNotReady;
use App\Exceptions\Cover\CoverRefused;
use App\Models\CoverOutcome;
use App\Models\Post;
use App\Services\Cover\NetworkCovers;
use App\Support\Media\CoverImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Gives a published video its Cover on a network that takes it only after
 * the video is up (`NetworkCovers`). It starts `FIRST_DELAY_SECONDS` after
 * the post publishes and, while the network is not ready, looks again on the
 * backoff below, about 30 minutes in all. A refusal, or the last try, records
 * the outcome `failed` and the post stays published: the post details say so,
 * and nothing is emailed. A success records `set`.
 */
class SetPostCover implements ShouldQueue
{
    use Queueable;

    public const int FIRST_DELAY_SECONDS = 60;

    /**
     * Seconds before each retry (the first try runs after `FIRST_DELAY_SECONDS`,
     * so the whole wait is 30 minutes).
     *
     * @var list<int>
     */
    public const array BACKOFF_SECONDS = [120, 180, 240, 300, 300, 300, 300];

    public int $tries = 8;

    public function __construct(public string $postId)
    {
    }

    public function handle(): void
    {
        $post = Post::query()->with('socialAccount')->find($this->postId);

        if (blank($post) || $post->status !== PostStatus::Published) {
            return;
        }

        $setter = NetworkCovers::for($post->platform);
        $cover = CoverImage::forPost($post);

        if ($setter === null || $cover === null) {
            return;
        }

        if ($post->coverOutcome?->outcome === Outcome::Set) {
            return;
        }

        try {
            $setter->set($post, $cover);
        } catch (CoverRefused $exception) {
            $this->record($post, Outcome::Failed, $exception);

            return;
        } catch (CoverNotReady $exception) {
            $this->retryOrFail($post, $exception);

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->retryOrFail($post, $exception);

            return;
        }

        $this->record($post, Outcome::Set);
    }

    private function retryOrFail(Post $post, Throwable $exception): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->record($post, Outcome::Failed, $exception);

            return;
        }

        $this->release(self::BACKOFF_SECONDS[min($this->attempts(), count(self::BACKOFF_SECONDS)) - 1]);
    }

    private function record(Post $post, Outcome $outcome, ?Throwable $exception = null): void
    {
        CoverOutcome::query()->updateOrCreate(
            ['post_id' => $post->id],
            ['outcome' => $outcome, 'attempts' => $this->attempts(), 'last_error' => $exception?->getMessage()],
        );
    }
}
