<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Enums\Alerts\AdminAlertSeverity;
use App\Models\AccountNetworkCall;
use App\Models\AdminAlert;
use App\Models\NetworkLimitSetting;
use App\Models\NightwatchIssue;
use App\Services\NetworkLimits\NetworkLimits;
use App\Services\Ops\RestoreDrillStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Gathers what the daily digest reports: TODAY alerts cleared in the last 24
 * hours, the Accounts that used a shared network bucket the previous UTC day,
 * Nightwatch issues opened in the last 24 hours, yesterday's failed jobs in
 * the digest timezone and whether a restore drill is due.
 */
class AdminAlertDigest
{
    public function __construct(
        private readonly RestoreDrillStatus $restoreDrills,
        private readonly NetworkLimits $networkLimits,
    ) {}

    /**
     * @return array{cleared: list<array{key: string, title: string}>, bucket_accounts: array{rows: list<array{network: string, account: string, owner: string|null, calls: int, percent: float}>, total: int}, issues: list<array{title: string, url: string|null}>, failed_jobs: int, restore_drill: array{due: bool, last_performed_at: string|null}}
     */
    public function items(): array
    {
        return [
            'cleared' => $this->clearedItems(),
            'bucket_accounts' => $this->bucketAccounts(),
            'issues' => $this->recentIssues(),
            'failed_jobs' => $this->yesterdaysFailedJobs(),
            'restore_drill' => $this->restoreDrill(),
        ];
    }

    /**
     * @param  array{cleared: list<array{key: string, title: string}>, bucket_accounts: array{rows: list<array<string, mixed>>, total: int}, issues: list<array<string, mixed>>, failed_jobs: int, restore_drill: array{due: bool, last_performed_at: string|null}}  $items
     */
    public function isEmpty(array $items): bool
    {
        return $items['cleared'] === []
            && $items['bucket_accounts']['rows'] === []
            && $items['issues'] === []
            && $items['failed_jobs'] === 0
            && ! $items['restore_drill']['due'];
    }

    /**
     * @return list<array{key: string, title: string}>
     */
    private function clearedItems(): array
    {
        return AdminAlert::query()
            ->where('is_open', false)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDay())
            ->orderBy('resolved_at')
            ->get()
            ->filter(fn (AdminAlert $alert): bool => $alert->alertKey()?->severity() === AdminAlertSeverity::Today)
            ->map(fn (AdminAlert $alert): array => [
                'key' => $alert->key,
                'title' => $alert->alertKey()?->title($alert->context ?? []) ?? $alert->key,
            ])
            ->unique('key')
            ->values()
            ->all();
    }

    /**
     * The digest runs at 01:00 UTC, so the bucket it reports is the one just
     * finished: 00:00 to 00:00 UTC the day before.
     *
     * @return array{rows: list<array{network: string, account: string, owner: string|null, calls: int, percent: float}>, total: int}
     */
    private function bucketAccounts(): array
    {
        $date = CarbonImmutable::now('UTC')->subDay()->startOfDay();
        $threshold = NetworkLimitSetting::current()->account_digest_percent;
        $rows = [];
        $total = 0;

        foreach ($this->networkLimits->networks() as $network) {
            $capacity = $this->networkLimits->capacity($network) ?? 0;

            if ($capacity <= 0) {
                continue;
            }

            $minimumCalls = (int) ceil($capacity * $threshold / 100);

            $this->networkLimits->accountRows($network, $date)
                ->where('account_network_calls.calls', '>=', $minimumCalls)
                ->get()
                ->each(function (AccountNetworkCall $row) use ($network, $capacity, &$rows, &$total): void {
                    $total++;

                    $rows[] = [
                        'network' => $this->networkLimits->label($network),
                        'account' => (string) $row->name,
                        'owner' => $row->owner_email,
                        'calls' => (int) $row->calls,
                        'percent' => round($this->networkLimits->percent((int) $row->calls, $capacity), 1),
                    ];
                });
        }

        usort($rows, fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return [
            'rows' => array_slice($rows, 0, 20),
            'total' => $total,
        ];
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

    /**
     * @return array{due: bool, last_performed_at: string|null}
     */
    private function restoreDrill(): array
    {
        $last = $this->restoreDrills->lastPerformedAt();

        return [
            'due' => $this->restoreDrills->isDue(),
            'last_performed_at' => $last?->timezone((string) config('rxdesk.alerts.timezone'))->format('Y-m-d'),
        ];
    }
}
