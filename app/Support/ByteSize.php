<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Human-readable sizes for the System admin ("5GB", "500 MB", "1.5T"), in
 * binary units like `Number::fileSize()`: 1GB is 1024MB.
 */
final class ByteSize
{
    private const array UNITS = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3, 'T' => 4];

    /**
     * The size in bytes, or null when the text is not a size.
     */
    public static function parse(?string $text): ?int
    {
        $matched = preg_match('/^(\d+(?:\.\d+)?)\s*([KMGT]?)(?:I?B)?$/', Str::upper(Str::trim((string) $text)), $parts);

        if ($matched !== 1) {
            return null;
        }

        return (int) round((float) $parts[1] * (1024 ** self::UNITS[$parts[2]]));
    }

    public static function format(int $bytes): string
    {
        return Number::fileSize($bytes, maxPrecision: 1);
    }
}
