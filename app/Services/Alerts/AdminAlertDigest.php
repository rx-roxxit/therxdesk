<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Enums\Alerts\AdminAlertSeverity;
use App\Models\AdminAlert;
use App\Models\NightwatchIssue;
use Illuminate\Support\Facades\DB;

/**
 * Gathers what the daily digest reports: TODAY alerts cleared in the last 24
 * hours, Nightwatch issues opened in the last 24 hours, and yesterday's failed
 * jobs in the digest timezone. Restore drills and bucket counters belong to
 * other tickets.
 */
class AdminAlertDigest
{
    /**
     * @return array{cleared: list<string>, issues: list<array{title: string, url: string|null}>, failed_jobs: int}
     */
    public function items(): array
    {
        return [
            'cleared' => $this->clearedKeys(),
            'issues' => $this->recentIssues(),
            'failed_jobs' => $this->yesterdaysFailedJobs(),
        ];
    }

    /**
     * @param  array{cleared: list<string>, issues: list<array<string, mixed>>, failed_jobs: int}  $items
     */
    public function isEmpty(array $items): bool
    {
        return $items['cleared'] === [] && $items['issues'] === [] && $items['failed_jobs'] === 0;
    }

    /**
     * @return list<string>
     */
    private function clearedKeys(): array
    {
        return AdminAlert::query()
            ->where('is_open', false)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDay())
            ->orderBy('resolved_at')
            ->get()
            ->filter(fn (AdminAlert $alert): bool => $alert->alertKey()?->severity() === AdminAlertSeverity::Today)
            ->map(fn (AdminAlert $alert): string => $alert->key)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, url: string|null}>
     */
    private function recentIssues(): array
    {
        return NightwatchIssue::query()
            ->where('opened_at', '>=', now()->subDay())
            ->latest('opened_at')
            ->limit(20)
            ->get()
            ->map(fn (NightwatchIssue $issue): array => [
                'title' => $issue->title,
                'url' => $issue->url,
            ])
            ->values()
            ->all();
    }

    private function yesterdaysFailedJobs(): int
    {
        $yesterday = now((string) config('rxdesk.alerts.timezone'))->subDay();
        $start = $yesterday->copy()->startOfDay()->utc();
        $end = $yesterday->copy()->endOfDay()->utc();

        return DB::table('failed_jobs')
            ->whereBetween('failed_at', [$start, $end])
            ->count();
    }
}
