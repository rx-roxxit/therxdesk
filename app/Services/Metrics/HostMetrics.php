<?php

declare(strict_types=1);

namespace App\Services\Metrics;

/**
 * The host's memory and load, as the capacity check reads them from inside the
 * container. Tests bind a fake in its place.
 */
interface HostMetrics
{
    /**
     * Used memory as a percentage of total memory, 0 to 100.
     */
    public function usedMemoryPercent(): float;

    /**
     * The 5-minute load average.
     */
    public function loadAverage5(): float;
}
