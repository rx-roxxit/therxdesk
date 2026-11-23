<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Support chat transcript for the System admin: the customer's words, the
 * answer, the vote and the context that is useful for improving the help
 * pages. Read-only.
 *
 * @mixin SupportConversation
 */
class SupportTranscriptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account' => $this->account ? [
                'id' => $this->account->id,
                'name' => $this->account->name,
            ] : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'plan' => $this->account?->plan?->name,
            'last_page' => $this->last_page,
            'escalated_at' => $this->escalated_at,
            'last_message_at' => $this->last_message_at,
            'messages' => $this->messages
                ->map(fn (SupportMessage $message): array => [
                    'id' => $message->id,
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'feedback' => $message->feedback?->value,
                    'created_at' => $message->created_at,
                ])
                ->values()
                ->all(),
        ];
    }
}
