<?php

declare(strict_types=1);

namespace App\Services\Metrics;

/**
 * How long the queue is taking to clear. `available` is false when the waits
 * could not be read, so callers do not mistake an empty map for a healthy
 * queue.
 */
final readonly class QueueHealth
{
    /**
     * @param  array<string, float>  $waits  seconds to clear, keyed by "connection:queue"
     */
    public function __construct(
        public array $waits,
        public bool $available,
    ) {}

    public function maxWaitSeconds(): float
    {
        if ($this->waits === []) {
            return 0.0;
        }

        return (float) max($this->waits);
    }
}
