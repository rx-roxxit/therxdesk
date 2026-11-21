<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Models\RestoreDrill;
use Carbon\CarbonImmutable;

/**
 * When the last restore drill happened and when the next one is due. A drill
 * is due when none is recorded or the latest one is older than the configured
 * months.
 */
class RestoreDrillStatus
{
    public function lastPerformedAt(): ?CarbonImmutable
    {
        $drill = RestoreDrill::query()->latest('performed_at')->first();

        return $drill?->performed_at;
    }

    public function dueAt(): ?CarbonImmutable
    {
        $last = $this->lastPerformedAt();

        if ($last === null) {
            return null;
        }

        return $last->addMonthsNoOverflow((int) config('rxdesk.backup.restore_drill_months'));
    }

    public function isDue(): bool
    {
        $dueAt = $this->dueAt();

        if ($dueAt === null) {
            return true;
        }

        return $dueAt->isPast();
    }
}
