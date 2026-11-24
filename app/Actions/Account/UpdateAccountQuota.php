<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Audit\RecordAuditEntry;
use App\Models\Account;
use App\Services\Storage\AccountStorage;

/**
 * Sets or clears an Account's own Quota. Null goes back to the Plan's (or the
 * default). The System admin's write is audited.
 */
class UpdateAccountQuota
{
    public function __construct(private readonly AccountStorage $storage) {}

    public function execute(Account $account, ?int $bytes, string $actor): Account
    {
        $before = $this->snapshot($account);

        $this->storage->setQuota($account, $bytes);

        RecordAuditEntry::execute($actor, 'account.quota.override', $account, $before, $this->snapshot($account));

        return $account;
    }

    /**
     * @return array{quota_bytes: int|null}
     */
    private function snapshot(Account $account): array
    {
        if ($account->quota_bytes === null) {
            return ['quota_bytes' => null];
        }

        return ['quota_bytes' => (int) $account->quota_bytes];
    }
}
