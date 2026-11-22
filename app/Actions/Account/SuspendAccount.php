<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Audit\RecordAuditEntry;
use App\Models\Account;

/**
 * The System admin turns an Account off through /internal/v1. The reason is
 * internal only: it is stored for the admin and recorded in the audit entry,
 * never shown to a customer.
 */
class SuspendAccount
{
    public function execute(Account $account, string $reason, string $actor): Account
    {
        $before = $this->snapshot($account);

        $account->forceFill([
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ])->save();

        RecordAuditEntry::execute($actor, 'account.suspend', $account, $before, $this->snapshot($account));

        return $account->fresh();
    }

    /**
     * @return array{suspended_at: string|null, suspension_reason: string|null}
     */
    private function snapshot(Account $account): array
    {
        return [
            'suspended_at' => $account->suspended_at?->toIso8601String(),
            'suspension_reason' => $account->suspension_reason,
        ];
    }
}
