<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class RecordAuditEntry
{
    private const array SECRET_KEYS = [
        'api_key',
        'api_token',
        'remember_token',
        'secret',
        'token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    public static function execute(string $actor, string $action, ?Model $target = null, array $before = [], array $after = []): AuditLog
    {
        $scrubbedBefore = self::scrub($before);
        $scrubbedAfter = self::scrub($after);

        return AuditLog::forceCreate([
            'actor' => $actor,
            'action' => $action,
            'target_type' => $target?->getTable(),
            'target_id' => $target !== null ? (string) $target->getKey() : null,
            'before' => $scrubbedBefore === [] ? null : $scrubbedBefore,
            'after' => $scrubbedAfter === [] ? null : $scrubbedAfter,
        ]);
    }

    private static function scrub(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);

            if (self::shouldScrub($lower)) {
                continue;
            }

            if (is_array($value)) {
                $nested = self::scrub($value);
                $result[$key] = $nested;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Drop passwords (including `password_confirmation`), known secret names
     * and any `_token` / `_secret` suffix. A business value such as
     * `tokens_per_credit` stays.
     */
    private static function shouldScrub(string $key): bool
    {
        if (str_contains($key, 'password')) {
            return true;
        }

        if (in_array($key, self::SECRET_KEYS, true)) {
            return true;
        }

        return str_ends_with($key, '_token') || str_ends_with($key, '_secret');
    }
}
