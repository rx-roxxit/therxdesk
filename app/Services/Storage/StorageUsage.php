<?php

declare(strict_types=1);

namespace App\Services\Storage;

/**
 * An account's Storage used and Quota, in bytes.
 */
final readonly class StorageUsage
{
    public function __construct(
        public int $used,
        public int $limit,
    ) {}

    public function remaining(): int
    {
        return max(0, $this->limit - $this->used);
    }

    public function fits(int $bytes): bool
    {
        return $this->used + $bytes <= $this->limit;
    }
}
