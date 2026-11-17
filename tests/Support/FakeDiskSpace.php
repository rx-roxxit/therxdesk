<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Storage\DiskSpace;

/**
 * A disk whose free and total space the test chooses.
 */
class FakeDiskSpace implements DiskSpace
{
    /**
     * The free space every test starts with: far above the Disk guard's
     * threshold, so only a test about the guard ever meets it.
     */
    private const int ROOMY_FREE_BYTES = 500 * 1024 ** 3;

    public function __construct(public int $free, public int $total) {}

    /**
     * Binds the disk every test starts with (`tests/Pest.php`).
     */
    public static function bindRoomy(): self
    {
        return self::bind(free: self::ROOMY_FREE_BYTES);
    }

    /**
     * Binds a disk with `$free` bytes free in the container and returns it.
     */
    public static function bind(int $free, ?int $total = null): self
    {
        $disk = new self($free, $total ?? max($free, 1024 ** 4));
        app()->instance(DiskSpace::class, $disk);

        return $disk;
    }

    public function free(): int
    {
        return $this->free;
    }

    public function total(): int
    {
        return $this->total;
    }
}
