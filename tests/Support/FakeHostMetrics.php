<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Metrics\HostMetrics;

/**
 * Host memory and load a test chooses, in place of `/proc`.
 */
class FakeHostMetrics implements HostMetrics
{
    public function __construct(
        public float $memoryPercent = 0.0,
        public float $load = 0.0,
    ) {}

    public static function bind(float $memoryPercent = 0.0, float $load = 0.0): self
    {
        $metrics = new self($memoryPercent, $load);
        app()->instance(HostMetrics::class, $metrics);

        return $metrics;
    }

    public function usedMemoryPercent(): float
    {
        return $this->memoryPercent;
    }

    public function loadAverage5(): float
    {
        return $this->load;
    }
}
