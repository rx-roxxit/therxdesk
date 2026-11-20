<?php

declare(strict_types=1);

namespace App\Actions\Landing;

use App\Enums\SocialAccount\Platform;

/**
 * The page props of the public landing. Everything the page shows that can
 * change without a deploy is read here at request time: the networks come
 * from the same switches the connect dialog reads.
 */
class BuildLandingProps
{
    /**
     * @return array{networks: list<array{value: string, label: string}>}
     */
    public static function execute(): array
    {
        return [
            'networks' => self::networks(),
        ];
    }

    /**
     * Every network a visitor could connect today, one entry per network
     * (LinkedIn profile and page, Instagram and Instagram through Facebook
     * collapse into one), in the connect dialog's order.
     *
     * @return list<array{value: string, label: string}>
     */
    private static function networks(): array
    {
        return array_map(
            fn (array $option): array => ['value' => $option['value'], 'label' => $option['label']],
            Platform::connectableOptions(),
        );
    }
}
