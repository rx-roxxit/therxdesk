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
 * the space the disk can give to media, and warns when they add up to more.
 */
class QuotaCommand extends Command
{
    private const string DEFAULT_SIZE = 'default';

    /**
     * @var list<string>
     */
    private const array ACTIONS = ['show', 'set'];

    protected $signature = 'rxdesk:quota
        {action : show or set}
        {email : The Owner\'s email}
        {size? : The new Quota, such as 20GB or 500MB, or "default"}';

    protected $description = "Show or set an Owner's storage Quota";

    public function handle(AccountStorage $storage): int
    {
        $action = (string) $this->argument('action');

        if (! collect(self::ACTIONS)->contains($action)) {
            $this->error("Unknown action \"{$action}\". Use show or set.");

            return self::FAILURE;
        }

        $account = $this->ownerAccount($storage);

        if (blank($account)) {
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
        $source = blank($account->quota_bytes) ? ' (default Quota)' : '';

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

        if (blank($bytes)) {
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

        if (blank($user)) {
            $this->error("No user with the email {$this->email()}.");

            return null;
        }

        $account = $storage->ownerAccountOf($user);

        if (blank($account)) {
            $this->error("{$this->email()} owns no workspaces, so they have no Quota.");
        }

        return $account;
    }

    /**
     * Compares the Quotas handed out with the space media can actually use:
     * free disk plus what the Owners store, minus the Disk guard threshold.
     */
    private function reportCommitment(AccountStorage $storage): void
    {
        $usable = $storage->usableSize();
        $total = $storage->totalQuota();

        if ($total > $usable) {
            $this->warn("Warning: the Quotas add up to {$this->size($total)}, more than the {$this->size($usable)} the disk can give to media (free disk plus Storage used, minus the Disk guard threshold).");

            return;
        }

        $this->line("The Quotas add up to {$this->size($total)}, within the {$this->size($usable)} the disk can give to media.");
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
