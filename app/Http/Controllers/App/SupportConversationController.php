<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Resources\App\Support\SupportMessageResource;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Support\SupportChat\SupportChatLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What to reopen on the next visit, and the explicit "New conversation". A
 * user only ever reaches their own.
 */
class SupportConversationController extends Controller
{
    public function __construct(private readonly SupportChatLimits $limits) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversation = SupportConversation::query()
            ->forUser($user)
            ->resumable(now()->subHours(SupportConversation::RESUME_HOURS))
            ->with('messages')
            ->latest('last_message_at')
            ->first();

        return response()->json($this->payload($conversation, $request));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversation = $this->reusableConversation($user) ?? SupportConversation::query()->create([
            'account_id' => $user->account_id,
            'user_id' => $user->id,
            'last_message_at' => now(),
        ]);

        return response()->json($this->payload($conversation, $request));
    }

    /**
     * The latest conversation when nothing was written in it, so clicking New
     * twice leaves no row behind.
     */
    private function reusableConversation(User $user): ?SupportConversation
    {
        $conversation = SupportConversation::query()
            ->forUser($user)
            ->withCount('messages')
            ->latest('last_message_at')
            ->first();

        if ($conversation !== null && $conversation->messages_count === 0) {
            return $conversation;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(?SupportConversation $conversation, Request $request): array
    {
        return [
            'conversation' => $conversation === null ? null : [
                'id' => $conversation->id,
                'last_page' => $conversation->last_page,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ],
            'messages' => $conversation === null
                ? []
                : $conversation->messages->map(fn (SupportMessage $message): array => SupportMessageResource::make($message))->values()->all(),
            'limits' => $this->limits->state($conversation, $request->user()),
        ];
    }
}
