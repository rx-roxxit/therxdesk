<?php

declare(strict_types=1);

namespace App\Services\NetworkLimits;

use App\Models\AccountNetworkCall;
use App\Models\NetworkCallBucket;
use App\Models\NetworkLimitSetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The read model behind the alerts and the admin API: today's UTC bucket, its
 * capacity from configuration, and which Accounts used it.
 */
class NetworkLimits
{
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->startOfDay();
    }

    public function resetAt(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now('UTC'))->startOfDay()->addDay();
    }

    /**
     * @return list<string>
     */
    public function networks(): array
    {
        return array_values(array_filter(
            array_keys((array) config('rxdesk.network_limits.buckets', [])),
            fn (string $network): bool => $this->capacity($network) !== null,
        ));
    }

    public function capacity(string $network): ?int
    {
        $capacity = (int) config("rxdesk.network_limits.buckets.{$network}", 0);

        return $capacity > 0 ? $capacity : null;
    }

    public function percent(int $calls, int $capacity): float
    {
        if ($capacity <= 0) {
            return 0.0;
        }

        return ($calls / $capacity) * 100;
    }

    /**
     * The capacity is the rule, not the `exhausted_at` stamp: raising a
     * network's budget lets publishing resume the same day instead of waiting
     * for the next UTC reset. A network with no registered capacity has no
     * bucket and is never exhausted.
     */
    public function isExhausted(string $network): bool
    {
        $capacity = $this->capacity($network);

        if ($capacity === null) {
            return false;
        }

        $row = $this->bucketRow($network);

        return $row !== null && (int) $row->calls >= $capacity;
    }

    /**
     * @return array{network: string, label: string, calls: int, capacity: int, percent: float, status: string, exhausted_at: string|null, reset_at: string}
     */
    public function bucket(string $network): array
    {
        $capacity = $this->capacity($network) ?? 0;
        $row = $this->bucketRow($network);
        $calls = (int) ($row?->calls ?? 0);

        return [
            'network' => $network,
            'label' => $this->label($network),
            'calls' => $calls,
            'capacity' => $capacity,
            'percent' => $this->percent($calls, $capacity),
            'status' => $this->status($calls, $capacity),
            'exhausted_at' => $row?->exhausted_at?->toIso8601String(),
            'reset_at' => $this->resetAt()->toIso8601String(),
        ];
    }

    /**
     * @return list<array{network: string, label: string, calls: int, capacity: int, percent: float, status: string, exhausted_at: string|null, reset_at: string}>
     */
    public function buckets(): array
    {
        return array_map(
            fn (string $network): array => $this->bucket($network),
            $this->networks(),
        );
    }

    public function label(string $network): string
    {
        return match ($network) {
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'pinterest' => 'Pinterest',
            default => ucfirst($network),
        };
    }

    /**
     * @return list<array{account_id: string, name: string, owner_email: string|null, calls: int, percent: float}>
     */
    public function topAccounts(string $network, int $limit): array
    {
        $capacity = $this->capacity($network) ?? 0;

        return $this->accountRows($network)
            ->limit($limit)
            ->get()
            ->map(fn (AccountNetworkCall $row): array => [
                'account_id' => (string) $row->account_id,
                'name' => (string) $row->name,
                'owner_email' => $row->owner_email,
                'calls' => (int) $row->calls,
                'percent' => $this->percent((int) $row->calls, $capacity),
            ])
            ->values()
            ->all();
    }

    /**
     * The Accounts behind one network's bucket, biggest caller first, as a
     * query the caller paginates with the configured page size.
     *
     * @return Builder<AccountNetworkCall>
     */
    public function accountRows(string $network, ?CarbonImmutable $date = null): Builder
    {
        return AccountNetworkCall::query()
            ->join('accounts', 'accounts.id', '=', 'account_network_calls.account_id')
            ->leftJoin('users', 'users.id', '=', 'accounts.owner_id')
            ->where('account_network_calls.network', $network)
            ->where('account_network_calls.date', ($date ?? $this->today())->toDateString())
            ->select([
                'account_network_calls.account_id',
                'accounts.name',
                'users.email as owner_email',
                'account_network_calls.calls',
            ])
            ->orderByDesc('account_network_calls.calls')
            ->orderBy('account_network_calls.account_id');
    }

    private function status(int $calls, int $capacity): string
    {
        if ($capacity > 0 && $calls >= $capacity) {
            return 'exhausted';
        }

        $setting = NetworkLimitSetting::current();
        $percent = $this->percent($calls, $capacity);

        if ($percent >= $setting->urgent_percent) {
            return 'urgent';
        }

        if ($percent >= $setting->today_percent) {
            return 'today';
        }

        return 'normal';
    }

    private function bucketRow(string $network): ?NetworkCallBucket
    {
        return NetworkCallBucket::query()
            ->where('network', $network)
            ->where('date', $this->today()->toDateString())
            ->first();
    }
}
