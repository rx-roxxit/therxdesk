<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Metrics\QueueHealth;
use App\Services\Metrics\QueueMetrics;

/**
 * Queue waits a test chooses, in place of Horizon.
 */
class FakeQueueMetrics implements QueueMetrics
{
    /**
     * @param  array<string, float>  $waits
     */
    public function __construct(
        public array $waits = [],
        public bool $available = true,
    ) {}

    /**
     * @param  array<string, float>  $waits
     */
    public static function bind(array $waits = [], bool $available = true): self
    {
        $metrics = new self($waits, $available);
        app()->instance(QueueMetrics::class, $metrics);

        return $metrics;
    }

    public function health(): QueueHealth
    {
        return new QueueHealth($this->waits, $this->available);
    }
}
