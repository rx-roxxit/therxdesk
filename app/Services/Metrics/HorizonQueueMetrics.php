<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use Illuminate\Support\Facades\Log;
use Laravel\Horizon\WaitTimeCalculator;
use Throwable;

/**
 * Reads the queue waits Horizon already computes. A failure (Redis down, no
 * supervisors reporting) marks the health unavailable instead of failing the
 * capacity check.
 */
class HorizonQueueMetrics implements QueueMetrics
{
    public function __construct(private readonly WaitTimeCalculator $calculator) {}

    public function health(): QueueHealth
    {
        try {
            $waits = $this->calculator->calculate();
            $seconds = [];

            foreach ($waits as $queue => $wait) {
                $seconds[(string) $queue] = (float) $wait;
            }

            return new QueueHealth($seconds, true);
        } catch (Throwable $exception) {
            Log::warning('Could not read the Horizon queue waits.', ['exception' => $exception]);

            return new QueueHealth([], false);
        }
    }
}
