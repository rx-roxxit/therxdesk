<?php

declare(strict_types=1);

namespace App\Support\PublicPages;

use Illuminate\Support\Uri;

/**
 * The absolute URLs of the public pages that crawlers and link previews read
 * (the canonical and Open Graph URL, robots.txt and the sitemap). They always
 * name the configured app URL, whatever host a request came in on, so a
 * crawler never indexes the site under another address.
 */
class PublicUrl
{
    /**
     * The absolute URL of a named route on the app URL.
     */
    public static function route(string $name): string
    {
        $app = Uri::of((string) config('app.url'));

        return Uri::route($name)
            ->withScheme((string) $app->scheme())
            ->withHost((string) $app->host())
            ->withPort($app->port())
            ->value();
    }
}
