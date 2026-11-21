<?php

declare(strict_types=1);

namespace App\Services\Ops;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pings the backup heartbeat only after a dump succeeds. The URL carries a
 * token, so a failed ping logs a fixed message and never the URL or the
 * exception.
 */
class BackupHeartbeat
{
    public function ping(): void
    {
        $url = config('rxdesk.heartbeat.backup_url');

        if (blank($url)) {
            return;
        }

        try {
            Http::timeout((int) config('rxdesk.heartbeat.timeout'))
                ->get((string) $url)
                ->throw();
        } catch (Throwable) {
            Log::warning('The backup heartbeat ping failed.');
        }
    }
}
