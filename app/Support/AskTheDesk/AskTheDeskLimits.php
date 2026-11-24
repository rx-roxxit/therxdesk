<?php

declare(strict_types=1);

namespace App\Support\AskTheDesk;

use App\Enums\AskTheDesk\AskTheDeskRole;
use App\Models\AskTheDeskMessage;
use Carbon\CarbonImmutable;

/**
 * The daily message cap of Ask the Desk. It bounds what one operator can cost
 * the service and clears at UTC midnight.
 */
final class AskTheDeskLimits
{
    public function max(): int
    {
        return max(1, (int) config('rxdesk.ask_the_desk.daily_limit'));
    }

    public function usedToday(): int
    {
        return AskTheDeskMessage::query()
            ->where('role', AskTheDeskRole::User)
            ->where('created_at', '>=', $this->dayStart())
            ->count();
    }

    /**
     * @return array{used: int, max: int, resets_at: string}
     */
    public function state(?int $used = null): array
    {
        return [
            'used' => $used ?? $this->usedToday(),
            'max' => $this->max(),
            'resets_at' => $this->resetsAt()->toIso8601String(),
        ];
    }

    public function secondsUntilReset(): int
    {
        return max(1, $this->resetsAt()->getTimestamp() - CarbonImmutable::now('UTC')->getTimestamp());
    }

    private function dayStart(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->startOfDay();
    }

    private function resetsAt(): CarbonImmutable
    {
        return $this->dayStart()->addDay();
    }
}
