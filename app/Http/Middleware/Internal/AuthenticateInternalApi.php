<?php

declare(strict_types=1);

namespace App\Http\Middleware\Internal;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateInternalApi
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $adminToken = trim((string) config('rxdesk.internal.admin_token', ''));
        $siteToken = trim((string) config('rxdesk.internal.site_token', ''));
        $given = (string) $request->bearerToken();

        if ($adminToken === '' && $siteToken === '') {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($given === '') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $isAdmin = $adminToken !== '' && hash_equals($adminToken, $given);
        $isSite = $siteToken !== '' && hash_equals($siteToken, $given);

        if (! $isAdmin && ! $isSite) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($isAdmin) {
            $email = trim((string) $request->header('X-Internal-Admin-Email'));

            if ($role === 'admin') {
                $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);

                if ($validator->fails()) {
                    return response()->json(['message' => 'A valid X-Internal-Admin-Email header is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $request->attributes->set('internal.role', 'admin');
            $request->attributes->set('internal.actor', $email);
        } else {
            if ($role === 'admin') {
                abort(Response::HTTP_NOT_FOUND);
            }

            $request->attributes->set('internal.role', 'site');
        }

        return $next($request);
    }

    public static function actor(Request $request): string
    {
        return (string) $request->attributes->get('internal.actor', 'cli');
    }
}
