<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PublicPages\PublicUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

/**
 * `/sitemap.xml`: the public pages, as absolute URLs on the configured app
 * URL. English only: the pages have one URL each, whatever the language.
 * The XML declaration is written here rather than in the view, because Blade
 * leaves anything opened with `<?` uncompiled.
 */
class SitemapController extends Controller
{
    /** The routes of the public pages, in the order the sitemap lists them. */
    private const array PUBLIC_PAGES = ['app.home', 'legal.terms', 'legal.privacy'];

    public function __invoke(): Response
    {
        $urlset = view('sitemap', [
            'urls' => Arr::map(self::PUBLIC_PAGES, fn (string $name): string => PublicUrl::route($name)),
        ])->render();

        return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n{$urlset}")
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
