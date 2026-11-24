<?php

declare(strict_types=1);

namespace App\Support\Ops;

use App\Enums\Post\PublishStatus;
use App\Models\Post;
use App\Services\Ai\AiBalances;
use App\Services\Storage\AccountStorage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The figures the admin app's Overview reads from `/internal/v1/overview`:
 * publishing over the last 14 days, usage totals across Owner Accounts and
 * the Accounts storing the most. Platform buckets are not here; the Overview
 * reads them from `/internal/v1/network-limits`. Nothing in the payload names
 * a customer's post or its text.
 */
final class OverviewReport
{
    /**
     * How many Accounts the largest-Accounts table lists.
     */
    public const int LARGEST_ACCOUNTS_LIMIT = 10;

    /**
     * Days in the publishing series, today included.
     */
    private const int PUBLISHING_DAYS = 14;

    public function __construct(
        private readonly AccountStorage $storage,
        private readonly AiBalances $aiBalances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonInterface $at): array
    {
        $today = CarbonImmutable::parse($at->toDateString(), 'UTC')->startOfDay();
        $accounts = $this->accountUsage();

        return [
            'generated_at' => $at->toIso8601String(),
            'today' => $today->toDateString(),
            'publishing' => $this->publishing($today),
            'usage' => [
                'storage' => $this->storageUsage($accounts),
                'ai_credits' => $this->aiCreditsUsage(),
            ],
            'largest_accounts' => $this->largestAccounts($accounts),
        ];
    }

    /**
     * Each Owner Account's Storage used and Quota, read once for the usage
     * totals and the largest-Accounts table.
     *
     * @return list<array{account_id: string, name: string, used_bytes: int, quota_bytes: int}>
     */
    private function accountUsage(): array
    {
        $rows = [];

        foreach ($this->storage->ownerAccounts() as $account) {
            $usage = $this->storage->usage($account);

            $rows[] = [
                'account_id' => $account->id,
                'name' => $account->name,
                'used_bytes' => $usage->used,
                'quota_bytes' => $usage->limit,
            ];
        }

        return $rows;
    }

    /**
     * One entry per day in the window, oldest first, zero-filled. Published
     * posts include those the network deleted since; failed posts are dated
     * by when their last attempt settled.
     *
     * @return list<array{date: string, published: int, failed: int}>
     */
    private function publishing(CarbonImmutable $today): array
    {
        $start = $today->subDays(self::PUBLISHING_DAYS - 1);
        $end = $today->endOfDay();

        $days = [];

        for ($date = $start; $date->lessThanOrEqualTo($today); $date = $date->addDay()) {
            $days[$date->toDateString()] = [
                'date' => $date->toDateString(),
                'published' => 0,
                'failed' => 0,
            ];
        }

        foreach (Post::query()->wentOut()->whereBetween('published_at', [$start, $end])->get(['published_at']) as $post) {
            $date = $post->published_at?->utc()->toDateString();

            if ($date !== null && isset($days[$date])) {
                $days[$date]['published']++;
            }
        }

        $failed = Post::query()
            ->where('publish_status', PublishStatus::Failed)
            ->whereBetween('publication_updated_at', [$start, $end])
            ->get(['publication_updated_at']);

        foreach ($failed as $post) {
            $date = $post->publication_updated_at?->utc()->toDateString();

            if ($date !== null && isset($days[$date])) {
                $days[$date]['failed']++;
            }
        }

        return array_values($days);
    }

    /**
     * @param  list<array{account_id: string, name: string, used_bytes: int, quota_bytes: int}>  $accounts
     * @return array{used_bytes: int, quota_bytes: int, percent: float}
     */
    private function storageUsage(array $accounts): array
    {
        $used = 0;
        $quota = 0;

        foreach ($accounts as $account) {
            $used += $account['used_bytes'];
            $quota += $account['quota_bytes'];
        }

        return [
            'used_bytes' => $used,
            'quota_bytes' => $quota,
            'percent' => $this->percent($used, $quota),
        ];
    }

    /**
     * The same per-Account allowances and usage that `/internal/v1/ai/balances`
     * lists, added up.
     *
     * @return array{used: int, allowance: int, percent: float}
     */
    private function aiCreditsUsage(): array
    {
        $used = 0;
        $allowance = 0;

        foreach ($this->aiBalances->rows() as $row) {
            $allowance += (int) data_get($row, 'monthly_credits');
            $used += (int) data_get($row, 'used');
        }

        return [
            'used' => $used,
            'allowance' => $allowance,
            'percent' => $this->percent($used, $allowance),
        ];
    }

    /**
     * @param  list<array{account_id: string, name: string, used_bytes: int, quota_bytes: int}>  $accounts
     * @return list<array{account_id: string, name: string, used_bytes: int, quota_bytes: int, percent: float}>
     */
    private function largestAccounts(array $accounts): array
    {
        usort($accounts, function (array $a, array $b): int {
            $byUsed = $b['used_bytes'] <=> $a['used_bytes'];

            if ($byUsed !== 0) {
                return $byUsed;
            }

            return strcmp($a['account_id'], $b['account_id']);
        });

        $rows = [];

        foreach (array_slice($accounts, 0, self::LARGEST_ACCOUNTS_LIMIT) as $account) {
            $rows[] = [
                ...$account,
                'percent' => $this->percent($account['used_bytes'], $account['quota_bytes']),
            ];
        }

        return $rows;
    }

    private function percent(int $used, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round($used / $total * 100, 1);
    }
}
