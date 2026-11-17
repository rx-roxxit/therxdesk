<?php

declare(strict_types=1);

namespace App\Services\Storage;

use RuntimeException;

/**
 * Reads the free and total space of the volume that holds the media storage
 * path: the root of the default filesystem disk when it is a local one, the
 * application's storage folder otherwise.
 */
class LocalDiskSpace implements DiskSpace
{
    public function free(): int
    {
        return $this->read(disk_free_space($this->path()));
    }

    public function total(): int
    {
        return $this->read(disk_total_space($this->path()));
    }

    private function path(): string
    {
        $disk = config('filesystems.default');
        $root = config("filesystems.disks.{$disk}.root");

        if (is_string($root) && is_dir($root)) {
            return $root;
        }

        return storage_path();
    }

    private function read(float|false $bytes): int
    {
        if ($bytes === false) {
            throw new RuntimeException("The disk space of {$this->path()} could not be read.");
        }

        return (int) $bytes;
    }
}
