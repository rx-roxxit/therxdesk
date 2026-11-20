<?php

declare(strict_types=1);

namespace App\Actions\Landing;

use App\Enums\SocialAccount\Platform;
use App\Support\PublicPages\PublicPageMeta;
use Illuminate\Support\Arr;

/**
 * The page props of the public landing. Everything the page shows that can
 * change without a deploy is read here at request time: the networks come
 * from the same switches the connect dialog reads.
 */
class BuildLandingProps
{
    /**
     * @return array{meta: array{title: string, document_title: string, site_name: string, description: string, canonical: string, image: array{url: string, width: string, height: string}|null}, networks: list<array{value: string, label: string}>}
     */
    public static function execute(): array
    {
        return [
            'meta' => PublicPageMeta::for('app.home', __('rxdesk.public.meta.landing')),
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
        return Arr::map(
            Platform::connectableOptions(),
            fn (array $option): array => Arr::only($option, ['value', 'label']),
        );
    }
}
