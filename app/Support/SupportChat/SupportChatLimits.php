<?php

declare(strict_types=1);

namespace App\Support\SupportChat;

use App\Enums\Support\SupportMessageRole;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The message caps that bound what the chat costs the service. These are not
 * AI credits, so the chat keeps working when the credits are gone.
 */
final class SupportChatLimits
{
    public const int PER_CONVERSATION = 20;

    public const int PER_DAY = 40;

    public const int PER_MINUTE = 10;

    public const int MAX_CHARACTERS = 2_000;

    public function usedInConversation(SupportConversation $conversation): int
    {
        return SupportMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', SupportMessageRole::User)
            ->count();
    }

    public function usedToday(User $user): int
    {
        return SupportMessage::query()
            ->where('role', SupportMessageRole::User)
            ->where('created_at', '>=', $this->dayStart())
            ->whereIn('conversation_id', SupportConversation::query()
                ->where('user_id', $user->id)
                ->select('id'))
            ->count();
    }

    /**
     * @return array{conversation: array{used: int, max: int}, daily: array{used: int, max: int, resets_at: string}}
     */
    public function state(?SupportConversation $conversation, User $user): array
    {
        return [
            'conversation' => [
                'used' => $conversation === null ? 0 : $this->usedInConversation($conversation),
                'max' => self::PER_CONVERSATION,
            ],
            'daily' => [
                'used' => $this->usedToday($user),
                'max' => self::PER_DAY,
                'resets_at' => $this->dayStart()->addDay()->toIso8601String(),
            ],
        ];
    }

    /**
     * Why the next message cannot be sent, or null when it can. The minute
     * limit is checked separately, because it clears by itself.
     *
     * @return array{code: string}|null
     */
    public function refusal(SupportConversation $conversation, User $user): ?array
    {
        if ($this->usedInConversation($conversation) >= self::PER_CONVERSATION) {
            return ['code' => 'conversation'];
        }

        if ($this->usedToday($user) >= self::PER_DAY) {
            return ['code' => 'daily'];
        }

        return null;
    }

    public function claimMinuteSlot(User $user): ?int
    {
        $key = $this->minuteKey($user);

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return max(1, RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 60);

        return null;
    }

    public function dayStart(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->startOfDay();
    }

    private function minuteKey(User $user): string
    {
        return "support-chat:{$user->id}";
    }
}
