<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Models\Account;
use App\Models\User;
use App\Services\Storage\AccountStorage;
use App\Support\ByteSize;
use Illuminate\Console\Command;

/**
 * The System admin's way to see and change an Owner's Quota, by email and in
 * human-readable sizes. Every change reports the Quotas handed out against
 * the usable storage, and warns when they add up to more.
 */
class QuotaCommand extends Command
{
    private const string DEFAULT_SIZE = 'default';

    protected $signature = 'rxdesk:quota
        {action : show or set}
        {email : The Owner\'s email}
        {size? : The new Quota, such as 20GB or 500MB, or "default"}';

    protected $description = "Show or set an Owner's storage Quota";

    public function handle(AccountStorage $storage): int
    {
        $action = (string) $this->argument('action');

        if (! in_array($action, ['show', 'set'], true)) {
            $this->error("Unknown action \"{$action}\". Use show or set.");

            return self::FAILURE;
        }

        $account = $this->ownerAccount($storage);

        if ($account === null) {
            return self::FAILURE;
        }

        if ($action === 'set') {
            return $this->set($storage, $account);
        }

        return $this->show($storage, $account);
    }

    private function show(AccountStorage $storage, Account $account): int
    {
        $usage = $storage->usage($account);
        $source = $account->quota_bytes === null ? ' (default Quota)' : '';

        $this->info("{$this->email()} uses {$this->size($usage->used)} of {$this->size($usage->limit)}{$source}.");
        $this->reportCommitment($storage);

        return self::SUCCESS;
    }

    private function set(AccountStorage $storage, Account $account): int
    {
        $size = (string) $this->argument('size');

        if (blank($size)) {
            $this->error('Pass the new Quota: rxdesk:quota set <email> <size>, such as 20GB, or "default".');

            return self::FAILURE;
        }

        if ($size === self::DEFAULT_SIZE) {
            $storage->setQuota($account, null);
            $this->info("{$this->email()} is back on the default Quota of {$this->size(AccountStorage::defaultQuota())}.");
            $this->reportCommitment($storage);

            return self::SUCCESS;
        }

        $bytes = ByteSize::parse($size);

        if ($bytes === null) {
            $this->error("\"{$size}\" is not a size. Write it like 20GB, 500MB or 1.5TB.");

            return self::FAILURE;
        }

        $storage->setQuota($account, $bytes);
        $this->info("{$this->email()} now has a Quota of {$this->size($bytes)}.");
        $this->reportCommitment($storage);

        return self::SUCCESS;
    }

    private function ownerAccount(AccountStorage $storage): ?Account
    {
        $user = User::query()->where('email', $this->email())->first();

        if ($user === null) {
            $this->error("No user with the email {$this->email()}.");

            return null;
        }

        $account = $storage->ownerAccountOf($user);

        if ($account === null) {
            $this->error("{$this->email()} owns no workspaces, so they have no Quota.");
        }

        return $account;
    }

    /**
     * Compares the Quotas handed out with the usable storage, when it is set.
     */
    private function reportCommitment(AccountStorage $storage): void
    {
        $usable = $storage->usableSize();

        if ($usable === null) {
            return;
        }

        $total = $storage->totalQuota();

        if ($total > $usable) {
            $this->warn("Warning: the Quotas add up to {$this->size($total)}, more than the {$this->size($usable)} of usable storage (RXDESK_USABLE_STORAGE).");

            return;
        }

        $this->line("The Quotas add up to {$this->size($total)}, within the {$this->size($usable)} of usable storage.");
    }

    private function email(): string
    {
        return (string) $this->argument('email');
    }

    private function size(int $bytes): string
    {
        return ByteSize::format($bytes);
    }
}
