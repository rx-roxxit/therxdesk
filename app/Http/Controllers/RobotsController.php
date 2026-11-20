<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PublicPages\PublicUrl;
use Illuminate\Http\Response;

/**
 * `/robots.txt`: every crawler may read every page, and the sitemap is named
 * on the configured app URL.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = PublicUrl::route('sitemap');

        return response("User-agent: *\nDisallow:\n\nSitemap: {$sitemap}\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
