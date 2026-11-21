<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Pings Better Stack's heartbeat from the `default` queue every five minutes.
 * The scheduler dispatches it; a blank URL turns it into a no-op.
 */
class PingHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(): void
    {
        $url = config('rxdesk.heartbeat.url');

        if (blank($url)) {
            return;
        }

        Http::timeout((int) config('rxdesk.heartbeat.timeout'))
            ->get((string) $url)
            ->throw();
    }
}
