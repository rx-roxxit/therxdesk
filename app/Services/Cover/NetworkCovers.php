<?php

declare(strict_types=1);

namespace App\Services\Cover;

use App\Enums\SocialAccount\Platform;

/**
 * The networks that take a Cover after publishing, and who sets it.
 */
class NetworkCovers
{
    /**
     * @var array<string, class-string<NetworkCover>>
     */
    private const array SETTERS = [
        'youtube' => YouTubeCover::class,
    ];

    public static function for(?Platform $platform): ?NetworkCover
    {
        if ($platform === null) {
            return null;
        }

        $setter = self::SETTERS[$platform->value] ?? null;

        if ($setter === null) {
            return null;
        }

        return app($setter);
    }
}
