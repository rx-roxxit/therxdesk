<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Support\SupportChatCounter;
use App\Enums\Support\SupportFeedback;
use App\Enums\Support\SupportMessageRole;
use App\Models\SupportMessage;
use App\Support\SupportChat\SupportChatCounters;

class SupportMessageObserver
{
    public function created(SupportMessage $message): void
    {
        if ($message->role !== SupportMessageRole::User) {
            return;
        }

        app(SupportChatCounters::class)->increment(
            SupportChatCounter::UserMessages,
            day: $message->created_at,
        );
    }

    /**
     * Only a change to a vote counts, so repeating the same feedback request
     * never adds a second count.
     */
    public function updated(SupportMessage $message): void
    {
        if (! $message->wasChanged('feedback')) {
            return;
        }

        $counter = match ($message->feedback) {
            SupportFeedback::Up => SupportChatCounter::FeedbackUp,
            SupportFeedback::Down => SupportChatCounter::FeedbackDown,
            null => null,
        };

        if ($counter === null) {
            return;
        }

        app(SupportChatCounters::class)->increment(
            $counter,
            day: $message->updated_at ?? $message->created_at,
        );
    }
}
