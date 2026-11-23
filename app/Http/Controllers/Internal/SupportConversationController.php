<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Resources\Internal\SupportTranscriptResource;
use App\Models\SupportConversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The System admin reads the Support chat transcripts, read-only. Nothing
 * older than the 30-day retention window is ever returned.
 */
class SupportConversationController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $feedback = $request->query('feedback');

        $conversations = SupportConversation::query()
            ->retained(now()->subDays(SupportConversation::RETENTION_DAYS))
            ->with(['account.plan', 'user', 'messages'])
            ->when(in_array($feedback, ['up', 'down'], true), function (Builder $query) use ($feedback): void {
                $query->whereHas('messages', fn (Builder $messages): Builder => $messages->where('feedback', $feedback));
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate((int) config('app.pagination.default'));

        return SupportTranscriptResource::collection($conversations);
    }
}
