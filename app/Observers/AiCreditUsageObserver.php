<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\Ai\AiFeature;
use App\Enums\Support\SupportChatCounter;
use App\Models\AiCreditUsage;
use App\Services\Ai\AiModelRegistry;
use App\Support\SupportChat\SupportChatCounters;

/**
 * The Support chat's own calls are not billed in credits, but their tokens and
 * list cost still count toward the business numbers.
 */
class AiCreditUsageObserver
{
    public function created(AiCreditUsage $usage): void
    {
        if ($usage->feature !== AiFeature::SupportChat) {
            return;
        }

        $counters = app(SupportChatCounters::class);
        $day = $usage->created_at;

        $counters->increment(SupportChatCounter::PromptTokens, $usage->prompt_tokens, $day);
        $counters->increment(SupportChatCounter::CompletionTokens, $usage->completion_tokens, $day);
        $counters->increment(SupportChatCounter::TotalTokens, $usage->total_tokens, $day);

        $cost = app(AiModelRegistry::class)->listCostMicros(
            $usage->model,
            $usage->prompt_tokens,
            $usage->completion_tokens,
        ) ?? 0;

        $counters->increment(SupportChatCounter::ListCostMicros, $cost, $day);
    }
}
