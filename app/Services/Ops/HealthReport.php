<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Models\BackupRun;
use App\Models\NightwatchIssue;
use App\Services\Metrics\QueueMetrics;
use App\Services\Storage\AccountStorage;
use Illuminate\Support\Facades\DB;

/**
 * The figures the admin app's health screen reads: queue, backup, restore
 * drill, disk and recent Nightwatch issues.
 */
class HealthReport
{
    public function __construct(
        private readonly QueueMetrics $queues,
        private readonly AccountStorage $storage,
        private readonly RestoreDrillStatus $restoreDrills,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'queue' => $this->queue(),
            'backup' => $this->backup(),
            'restore_drill' => $this->restoreDrill(),
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
     * @return array{last_run_at: string}|null
     */
    private function backup(): ?array
    {
        $run = BackupRun::query()->latest('completed_at')->first();

        if ($run === null) {
            return null;
        }

        return [
            'last_run_at' => $run->completed_at->toIso8601String(),
        ];
    }

    /**
     * @return array{last_performed_at: string|null, due_at: string|null, due: bool}
     */
    private function restoreDrill(): array
    {
        return [
            'last_performed_at' => $this->restoreDrills->lastPerformedAt()?->toIso8601String(),
            'due_at' => $this->restoreDrills->dueAt()?->toIso8601String(),
            'due' => $this->restoreDrills->isDue(),
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
