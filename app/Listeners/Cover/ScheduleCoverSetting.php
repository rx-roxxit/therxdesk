<?php

declare(strict_types=1);

namespace App\Listeners\Cover;

use App\Enums\Post\Status as PostStatus;
use App\Events\PostStatusChanged;
use App\Jobs\Cover\SetPostCover;
use App\Services\Cover\NetworkCovers;
use App\Support\Media\CoverImage;

/**
 * A post that just published, on a network that takes the Cover after the
 * video is up, and whose video has one, starts `SetPostCover`. Any other
 * status change, and any post without a Cover, starts nothing.
 */
class ScheduleCoverSetting
{
    public function handle(PostStatusChanged $event): void
    {
        if ($event->post->status !== PostStatus::Published) {
            return;
        }

        if ($event->previousStatus === PostStatus::Published) {
            return;
        }

        if (NetworkCovers::for($event->post->platform) === null) {
            return;
        }

        if (CoverImage::forPost($event->post) === null) {
            return;
        }

        SetPostCover::dispatch($event->post->id)->delay(now()->addSeconds(SetPostCover::FIRST_DELAY_SECONDS));
    }
}
