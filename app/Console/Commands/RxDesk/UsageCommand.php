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
 * Quota, with the totals.
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

            return self::SUCCESS;
        }

        $usages = $accounts->map(fn (Account $account): StorageUsage => $storage->usage($account));

        $this->table(
            ['Owner', 'Storage used', 'Quota', 'Used'],
            $accounts->map(fn (Account $account, int $index): array => [
                $this->ownerLabel($account),
                ByteSize::format($usages[$index]->used),
                $this->quotaLabel($account, $usages[$index]->limit),
                $this->share($usages[$index]),
            ])->all(),
        );

        $used = ByteSize::format((int) $usages->sum('used'));
        $quotas = ByteSize::format((int) $usages->sum('limit'));

        $this->info("Total: {$used} used of {$quotas} handed out.");

        return self::SUCCESS;
    }

    private function ownerLabel(Account $account): string
    {
        if ($account->owner === null) {
            return "{$account->name} (no owner)";
        }

        return "{$account->owner->name} <{$account->owner->email}>";
    }

    private function quotaLabel(Account $account, int $limit): string
    {
        if ($account->quota_bytes === null) {
            return ByteSize::format($limit).' (default)';
        }

        return ByteSize::format($limit);
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
