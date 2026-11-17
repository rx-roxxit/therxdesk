<?php

declare(strict_types=1);

namespace App\Services\Storage;

/**
 * The disk the media is stored on, in bytes. The Disk guard reads it, and
 * tests bind a fake in its place.
 */
interface DiskSpace
{
    public function free(): int;

    public function total(): int;
}
