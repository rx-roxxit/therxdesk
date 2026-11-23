<?php

declare(strict_types=1);

namespace App\Enums\Support;

/**
 * The daily Support chat counts. Each case is the column it increments in
 * `support_chat_daily_counters`; none of them names an Account or holds
 * content, so the counts outlive pruning and account deletion.
 */
enum SupportChatCounter: string
{
    case ConversationsStarted = 'conversations_started';
    case UserMessages = 'user_messages';
    case Handoffs = 'handoffs';
    case FeedbackUp = 'feedback_up';
    case FeedbackDown = 'feedback_down';
    case PromptTokens = 'prompt_tokens';
    case CompletionTokens = 'completion_tokens';
    case TotalTokens = 'total_tokens';
    case ListCostMicros = 'list_cost_micros';
}
