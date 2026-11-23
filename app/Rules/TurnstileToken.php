<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Checks the `cf-turnstile-response` token a Cloudflare Turnstile widget
 * issued against the siteverify endpoint. A missing secret key skips the
 * check, so a self-hosted install or a dev machine without keys keeps
 * upstream's open registration.
 */
class TurnstileToken implements ValidationRule
{
    public function __construct(private readonly ?string $ip = null) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('rxdesk.turnstile.secret_key');

        if (blank($secret)) {
            return;
        }

        if (! is_string($value) || blank($value)) {
            $fail(__('rxdesk.turnstile.failed'));

            return;
        }

        if (! $this->verifies($value, (string) $secret)) {
            $fail(__('rxdesk.turnstile.failed'));
        }
    }

    private function verifies(string $token, string $secret): bool
    {
        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post((string) config('rxdesk.turnstile.verify_url'), array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $this->ip,
                ], fn (mixed $value): bool => filled($value)));
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
