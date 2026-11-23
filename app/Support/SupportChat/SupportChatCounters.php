<?php

declare(strict_types=1);

namespace App\Support\SupportChat;

use App\Enums\Support\SupportChatCounter;
use App\Models\SupportChatDailyCounter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Writes and reads the daily Support chat counts. Every event adds to one
 * UTC day's row; a day only ever has one row, so retries must call this once
 * per event.
 */
final class SupportChatCounters
{
    public function increment(SupportChatCounter $counter, int $amount = 1, ?CarbonInterface $day = null): void
    {
        if ($amount === 0) {
            return;
        }

        $date = ($day ?? CarbonImmutable::now('UTC'))->toDateString();

        SupportChatDailyCounter::query()
            ->firstOrCreate(['date' => $date])
            ->add($counter, $amount);
    }

    /**
     * The daily rows from `$from` to `$to`, oldest first, for the Business
     * metrics reader. It carries counts only, never a customer or content.
     *
     * @return Collection<int, SupportChatDailyCounter>
     */
    public function between(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return SupportChatDailyCounter::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();
    }
}
