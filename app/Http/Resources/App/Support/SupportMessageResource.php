<?php

declare(strict_types=1);

namespace App\Http\Resources\App\Support;

use App\Models\SupportMessage;
use App\Support\SupportChat\SupportChatMarkdown;

class SupportMessageResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(SupportMessage $message): array
    {
        $isAnswer = $message->isAnswer();

        return [
            'id' => $message->id,
            'role' => $message->role->value,
            'created_at' => $message->created_at->toIso8601String(),
            'feedback' => $message->feedback?->value,
            'html' => $isAnswer ? app(SupportChatMarkdown::class)->render($message->content) : null,
            'text' => $isAnswer ? null : $message->content,
        ];
    }
}
