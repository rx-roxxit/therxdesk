<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Models\Account;
use App\Services\Storage\AccountStorage;
use App\Services\Storage\StorageUsage;
use App\Support\ByteSize;
use Illuminate\Console\Command;

/**
 * The System admin's view of who stores what: each Owner's Storage used and
 * Quota, with the totals and the state of the disk and the Disk guard.
 */
class UsageCommand extends Command
{
    protected $signature = 'rxdesk:usage';

    protected $description = "List each Owner's Storage used and Quota";

    public function handle(AccountStorage $storage): int
    {
        $accounts = $storage->ownerAccounts();

        if ($accounts->isEmpty()) {
            $this->info('No Owners yet.');
            $this->reportDisk($storage);

            return self::SUCCESS;
        }

        $usages = $accounts->map(fn (Account $account): StorageUsage => $storage->usage($account));

        $this->table(
            ['Owner', 'Storage used', 'Quota', 'Used'],
            $accounts->map(fn (Account $account, int $index): array => $this->row($account, $usages->get($index)))->all(),
        );

        $used = ByteSize::format((int) $usages->sum('used'));
        $quotas = ByteSize::format((int) $usages->sum('limit'));

        $this->info("Total: {$used} used of {$quotas} handed out.");
        $this->reportDisk($storage);

        return self::SUCCESS;
    }

    private function reportDisk(AccountStorage $storage): void
    {
        $disk = $storage->diskStatus();
        $state = $disk->isGuarding() ? 'ACTIVE: uploads and imports are refused' : 'inactive';

        $this->newLine();
        $total = ByteSize::format($disk->total);
        $free = ByteSize::format($disk->free);
        $threshold = ByteSize::format($disk->threshold);

        $this->line("Disk total: {$total}");
        $this->line("Disk free: {$free}");
        $this->line("Disk guard threshold: {$threshold}");
        $this->line("Disk guard: {$state}");
    }

    /**
     * @return list<string>
     */
    private function row(Account $account, StorageUsage $usage): array
    {
        return [
            $this->ownerLabel($account),
            ByteSize::format($usage->used),
            $this->quotaLabel($account, $usage->limit),
            $this->share($usage),
        ];
    }

    private function ownerLabel(Account $account): string
    {
        if (blank($account->owner)) {
            return "{$account->name} (no owner)";
        }

        return "{$account->owner->name} <{$account->owner->email}>";
    }

    private function quotaLabel(Account $account, int $limit): string
    {
        $formatted = ByteSize::format($limit);

        if (blank($account->quota_bytes)) {
            return "{$formatted} (default)";
        }

        return $formatted;
    }

    private function share(StorageUsage $usage): string
    {
        if ($usage->limit === 0) {
            return '-';
        }

        $percent = (int) round($usage->used / $usage->limit * 100);

        return "{$percent}%";
    }
}
