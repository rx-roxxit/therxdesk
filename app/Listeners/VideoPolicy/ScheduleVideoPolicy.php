<?php

declare(strict_types=1);

namespace App\Listeners\VideoPolicy;

use App\Enums\Post\Status as PostStatus;
use App\Events\PostStatusChanged;
use App\Jobs\VideoPolicy\ApplyVideoPolicy;

/**
 * A post that just published gets its videos handled one hour later, by the
 * Video policy in force then. Any other status change starts nothing.
 */
class ScheduleVideoPolicy
{
    public function handle(PostStatusChanged $event): void
    {
        if ($event->post->status !== PostStatus::Published) {
            return;
        }

        if ($event->previousStatus === PostStatus::Published) {
            return;
        }

        ApplyVideoPolicy::dispatch($event->post->id)->delay(now()->addMinutes(ApplyVideoPolicy::DELAY_MINUTES));
    }
}
