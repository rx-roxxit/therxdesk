<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Support\SupportChat\SupportChatGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Help menu reads the same gate (`HandleInertiaRequests`), so a customer
 * never sees an entry that leads nowhere.
 */
class EnsureSupportChatAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(SupportChatGate::class)->enabled(), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
