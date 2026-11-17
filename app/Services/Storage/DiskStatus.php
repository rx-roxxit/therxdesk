<?php

declare(strict_types=1);

namespace App\Services\Storage;

/**
 * The disk the media lives on and the Disk guard's threshold, in bytes.
 */
final readonly class DiskStatus
{
    public function __construct(
        public int $total,
        public int $free,
        public int $threshold,
    ) {}

    /**
     * Whether free space has dropped below the threshold, so uploads stop.
     */
    public function isGuarding(): bool
    {
        return $this->free < $this->threshold;
    }
}
