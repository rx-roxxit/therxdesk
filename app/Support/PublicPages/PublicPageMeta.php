<?php

declare(strict_types=1);

namespace App\Support\PublicPages;

use Illuminate\Support\Facades\Vite;

/**
 * What a public page tells search engines and link previews: its title,
 * description, canonical URL and Open Graph image. The page passes it as its
 * `meta` prop: the root view renders it in the HTML (`partials.public-meta`),
 * and `PublicPageHead.vue` keeps the head in step on visits inside the site.
 */
class PublicPageMeta
{
    /** The Open Graph image every public page shares: a real app screen with the RX mark. */
    private const string IMAGE = 'resources/js/assets/landing/og-image.jpg';

    /** Its size, as the tag content Open Graph reads. */
    private const string IMAGE_WIDTH = '1200';

    private const string IMAGE_HEIGHT = '630';

    /**
     * @param  string  $routeName  The page's route, its canonical URL.
     * @param  string  $title  The page's own title; empty shows the app name alone.
     * @return array{title: string, document_title: string, site_name: string, description: string, canonical: string, image: array{url: string, width: string, height: string}|null}
     */
    public static function for(string $routeName, string $description, string $title = ''): array
    {
        return [
            'title' => $title,
            'document_title' => self::documentTitle($title),
            'site_name' => self::siteName(),
            'description' => $description,
            'canonical' => PublicUrl::route($routeName),
            'image' => self::image(),
        ];
    }

    /**
     * The document title, written as the app's Inertia title callback writes
     * it (`resources/js/app.ts`), so the first paint and later visits agree.
     */
    private static function documentTitle(string $title): string
    {
        $siteName = self::siteName();

        if (blank($title)) {
            return $siteName;
        }

        return "{$title} - {$siteName}";
    }

    private static function siteName(): string
    {
        return (string) config('app.name');
    }

    /**
     * The image's absolute URL from the Vite build (where the build serves
     * it, a CDN or the dev server included), or null when the build does not
     * have it (tests fake the build), so no page names a broken image.
     *
     * @return array{url: string, width: string, height: string}|null
     */
    private static function image(): ?array
    {
        $asset = rescue(fn (): string => Vite::asset(self::IMAGE), report: false);

        if (blank($asset)) {
            return null;
        }

        return [
            'url' => $asset,
            'width' => self::IMAGE_WIDTH,
            'height' => self::IMAGE_HEIGHT,
        ];
    }
}
