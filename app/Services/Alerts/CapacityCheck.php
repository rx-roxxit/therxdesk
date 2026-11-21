<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Enums\Alerts\AdminAlertKey;
use App\Models\NightwatchIssue;
use App\Services\Metrics\HostMetrics;
use App\Services\Metrics\QueueHealth;
use App\Services\Metrics\QueueMetrics;
use App\Services\Storage\AccountStorage;
use App\Services\Storage\DiskStatus;
use App\Support\ByteSize;
use InvalidArgumentException;

/**
 * Reads the host, the queue, the disk and Nightwatch once and raises or clears
 * the matching alerts. Thresholds are configuration, not code.
 */
class CapacityCheck
{
    public function __construct(
        private readonly HostMetrics $host,
        private readonly QueueMetrics $queues,
        private readonly AccountStorage $storage,
        private readonly AdminAlertNotifier $notifier,
    ) {}

    public function run(): void
    {
        $this->checkRam($this->host->usedMemoryPercent());
        $this->checkLoad($this->host->loadAverage5());
        $this->checkWait($this->queues->health());
        $this->checkDisk($this->storage->diskStatus());
        $this->checkNightwatchBurst();
    }

    private function checkRam(float $percent): void
    {
        $threshold = (float) config('rxdesk.alerts.capacity.ram_percent');

        if ($percent > $threshold) {
            $this->notifier->raise(AdminAlertKey::CapacityRam, [
                'percent' => round($percent, 1),
                'threshold' => $threshold,
            ]);

            return;
        }

        $this->notifier->clear(AdminAlertKey::CapacityRam);
    }

    private function checkLoad(float $load): void
    {
        $threshold = (float) config('rxdesk.alerts.capacity.load');

        if ($load > $threshold) {
            $this->notifier->raise(AdminAlertKey::CapacityLoad, [
                'load' => round($load, 2),
                'threshold' => $threshold,
            ]);

            return;
        }

        $this->notifier->clear(AdminAlertKey::CapacityLoad);
    }

    private function checkWait(QueueHealth $queue): void
    {
        if (! $queue->available) {
            return;
        }

        $seconds = $queue->maxWaitSeconds();
        $threshold = (int) config('rxdesk.alerts.capacity.wait_seconds');

        if ($seconds > $threshold) {
            $this->notifier->raise(AdminAlertKey::CapacityWait, [
                'minutes' => round($seconds / 60, 1),
                'threshold_minutes' => round($threshold / 60, 1),
            ]);

            return;
        }

        $this->notifier->clear(AdminAlertKey::CapacityWait);
    }

    private function checkDisk(DiskStatus $disk): void
    {
        $margin = $this->size('disk_urgent_margin');

        // Near the guard threshold is URGENT, and suppresses the lower alert.
        if ($disk->free < $disk->threshold + $margin) {
            $this->notifier->raise(AdminAlertKey::DiskGuard, [
                'free' => ByteSize::format($disk->free),
                'threshold' => ByteSize::format($disk->threshold),
            ]);
            $this->notifier->clear(AdminAlertKey::CapacityDiskFree);

            return;
        }

        $this->notifier->clear(AdminAlertKey::DiskGuard);

        $lowFree = $this->size('disk_free');

        if ($disk->free < $lowFree) {
            $this->notifier->raise(AdminAlertKey::CapacityDiskFree, [
                'free' => ByteSize::format($disk->free),
                'threshold' => ByteSize::format($lowFree),
            ]);

            return;
        }

        $this->notifier->clear(AdminAlertKey::CapacityDiskFree);
    }

    private function size(string $key): int
    {
        $configured = (string) config("rxdesk.alerts.capacity.{$key}");

        return ByteSize::parse($configured)
            ?? throw new InvalidArgumentException("rxdesk.alerts.capacity.{$key} \"{$configured}\" is not a size such as 30GB.");
    }

    private function checkNightwatchBurst(): void
    {
        $threshold = (int) config('rxdesk.alerts.nightwatch.burst_threshold');
        $count = NightwatchIssue::query()
            ->where('opened_at', '>=', now()->subHour())
            ->count();

        if ($threshold > 0 && $count >= $threshold) {
            $this->notifier->raise(AdminAlertKey::NightwatchBurst, ['count' => $count]);

            return;
        }

        $this->notifier->clear(AdminAlertKey::NightwatchBurst);
    }
}
