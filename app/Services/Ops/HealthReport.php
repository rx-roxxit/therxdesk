<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Models\NightwatchIssue;
use App\Services\Metrics\QueueMetrics;
use App\Services\Storage\AccountStorage;
use Illuminate\Support\Facades\DB;

/**
 * The figures the admin app's health screen reads: queue, backup, disk and
 * recent Nightwatch issues.
 */
class HealthReport
{
    public function __construct(
        private readonly QueueMetrics $queues,
        private readonly AccountStorage $storage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'queue' => $this->queue(),
            // The hourly backup job fills this once it exists; until then there is no honest value.
            'backup' => null,
            'disk' => $this->disk(),
            'nightwatch' => $this->nightwatch(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        $health = $this->queues->health();

        return [
            'available' => $health->available,
            'waits' => $health->waits,
            'max_wait_seconds' => $health->maxWaitSeconds(),
            'failed' => [
                'total' => DB::table('failed_jobs')->count(),
                'last_24h' => DB::table('failed_jobs')
                    ->where('failed_at', '>=', now()->subDay())
                    ->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function disk(): array
    {
        $disk = $this->storage->diskStatus();

        return [
            'total_bytes' => $disk->total,
            'free_bytes' => $disk->free,
            'threshold_bytes' => $disk->threshold,
            'guarding' => $disk->isGuarding(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nightwatch(): array
    {
        return [
            'open' => NightwatchIssue::query()->whereNull('resolved_at')->count(),
            'recent' => NightwatchIssue::query()
                ->latest('opened_at')
                ->limit(10)
                ->get()
                ->map(fn (NightwatchIssue $issue): array => [
                    'id' => $issue->issue_id,
                    'ref' => $issue->ref,
                    'title' => $issue->title,
                    'type' => $issue->type,
                    'status' => $issue->status,
                    'url' => $issue->url,
                    'opened_at' => $issue->opened_at,
                    'resolved_at' => $issue->resolved_at,
                ])
                ->values()
                ->all(),
        ];
    }
}
