<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Nightwatch\RecordNightwatchEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nightwatch's webhook. The route sits outside the `web` group, so there is no
 * CSRF check; a shared secret in `Nightwatch-Signature` authenticates the call
 * instead. A blank secret makes the endpoint indistinguishable from an absent
 * one.
 */
class NightwatchWebhookController extends Controller
{
    public function __invoke(Request $request, RecordNightwatchEvent $record): Response
    {
        $secret = (string) config('rxdesk.nightwatch.webhook_secret');

        if (blank($secret)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $signature = trim((string) $request->header('Nightwatch-Signature', ''));
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $event = data_get($payload, 'event');
        $issueId = data_get($payload, 'payload.issue.id');

        if (! is_string($event) || $event === '' || blank($issueId)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $record->execute($event, (array) data_get($payload, 'payload', []));

        return response()->noContent(Response::HTTP_OK);
    }
}
