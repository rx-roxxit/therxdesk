<?php

declare(strict_types=1);

namespace App\Services\Metrics;

/**
 * The queue's current wait times. Tests bind a fake in its place.
 */
interface QueueMetrics
{
    public function health(): QueueHealth;
}
