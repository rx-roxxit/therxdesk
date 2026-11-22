<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Audit\RecordAuditEntry;
use App\Models\Account;
use Illuminate\Validation\ValidationException;

/**
 * The System admin lifts a Suspension through /internal/v1. The Account gets
 * its access back; a blocked post can then be retried.
 */
class LiftSuspension
{
    public function execute(Account $account, string $actor): Account
    {
        if (! $account->isSuspended()) {
            throw ValidationException::withMessages([
                'account' => 'This Account is not suspended.',
            ]);
        }

        $before = $this->snapshot($account);

        $account->forceFill([
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();

        RecordAuditEntry::execute($actor, 'account.lift', $account, $before, $this->snapshot($account));

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
