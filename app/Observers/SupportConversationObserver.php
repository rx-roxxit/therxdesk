<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Support\SupportChatCounter;
use App\Models\SupportConversation;
use App\Support\SupportChat\SupportChatCounters;

class SupportConversationObserver
{
    public function created(SupportConversation $conversation): void
    {
        app(SupportChatCounters::class)->increment(
            SupportChatCounter::ConversationsStarted,
            day: $conversation->created_at,
        );
    }
}
