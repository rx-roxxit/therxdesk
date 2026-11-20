<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The RX Desk: marks the public pages (the landing and the legal pages) with
 * the shared `publicPage` flag. The root view leaves Google Tag Manager out
 * of them and the app bootstrap starts no tracking there (`lib/tracking.ts`),
 * so a public page sets only the session and XSRF cookies. Every other page
 * keeps `publicPage` false from `HandleInertiaRequests`.
 */
class MarkPublicPage
{
    public function handle(Request $request, Closure $next): mixed
    {
        Inertia::share('publicPage', true);

        return $next($request);
    }
}
