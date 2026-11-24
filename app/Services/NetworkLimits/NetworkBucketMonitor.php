<?php

declare(strict_types=1);

namespace App\Services\NetworkLimits;

use App\Enums\Alerts\AdminAlertKey;
use App\Models\AdminAlert;
use App\Models\NetworkCallBucket;
use App\Models\NetworkLimitSetting;
use App\Services\Alerts\AdminAlertNotifier;
use Illuminate\Support\Str;

/**
 * Raises and clears the per-network bucket alerts. A bucket has one alert of
 * each level, scoped by the network key, so two networks never share a state
 * row. Exhaustion is recorded on the bucket itself, which is what stops a
 * publish until the next UTC day.
 */
class NetworkBucketMonitor
{
    public function __construct(
        private readonly NetworkLimits $limits,
        private readonly AdminAlertNotifier $notifier,
    ) {}

    public function evaluate(string $network): void
    {
        $capacity = $this->limits->capacity($network);

        if ($capacity === null) {
            return;
        }

        $bucket = $this->limits->bucket($network);
        $setting = NetworkLimitSetting::current();
        $context = $this->context($network, $bucket);

        if ($bucket['status'] === 'exhausted') {
            $this->markExhausted($network);
            $this->notifier->raise(AdminAlertKey::NetworkBucketExhausted, $context, $network);

            return;
        }

        if ($bucket['percent'] >= $setting->urgent_percent) {
            $this->notifier->raise(AdminAlertKey::NetworkBucketUrgent, $context, $network);

            return;
        }

        if ($bucket['percent'] >= $setting->today_percent) {
            $this->notifier->raise(AdminAlertKey::NetworkBucketToday, $context, $network);

            return;
        }

        $this->notifier->clear(AdminAlertKey::NetworkBucketToday, $network);
        $this->notifier->clear(AdminAlertKey::NetworkBucketUrgent, $network);
        $this->notifier->clear(AdminAlertKey::NetworkBucketExhausted, $network);
    }

    /**
     * The framing around every network, every five minutes: an alert opened on
     * an earlier bucket day describes a bucket that no longer exists.
     */
    public function evaluateAll(): void
    {
        $today = $this->limits->today();

        $stale = AdminAlert::query()
            ->where('is_open', true)
            ->where('opened_at', '<', $today)
            ->where('key', 'like', 'network_bucket_%')
            ->get();

        foreach ($stale as $alert) {
            $key = AdminAlertKey::tryFrom(Str::before($alert->key, ':'));

            if ($key === null) {
                continue;
            }

            $scope = Str::contains($alert->key, ':') ? Str::after($alert->key, ':') : null;

            $this->notifier->clear($key, $scope);
        }
    }

    /**
     * Called by the publishing gate as well, so an exhausted bucket always has
     * an open URGENT alert even when no counted call raised it.
     */
    public function raiseExhausted(string $network): void
    {
        $this->markExhausted($network);

        $this->notifier->raise(
            AdminAlertKey::NetworkBucketExhausted,
            $this->context($network, $this->limits->bucket($network)),
            $network,
        );
    }

    private function markExhausted(string $network): void
    {
        NetworkCallBucket::query()
            ->where('network', $network)
            ->where('date', $this->limits->today()->toDateString())
            ->whereNull('exhausted_at')
            ->update(['exhausted_at' => now()]);
    }

    /**
     * @param  array{network: string, label: string, calls: int, capacity: int, percent: float, status: string, exhausted_at: string|null, reset_at: string}  $bucket
     * @return array{network: string, calls: int, capacity: int, percent: float, reset: string}
     */
    private function context(string $network, array $bucket): array
    {
        return [
            'network' => $bucket['label'],
            'calls' => $bucket['calls'],
            'capacity' => $bucket['capacity'],
            'percent' => round($bucket['percent'], 1),
            'reset' => $this->limits->resetAt()
                ->timezone((string) config('rxdesk.alerts.timezone'))
                ->format('Y-m-d H:i T'),
        ];
    }
}
