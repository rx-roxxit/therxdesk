<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Audit\RecordAuditEntry;
use App\Models\Account;
use Illuminate\Validation\ValidationException;

/**
 * The System admin lifts a Suspension through /internal/v1. The Account gets
 * its access back; a blocked post can then be retried.
 *
 * The deletion clock was frozen for the length of the Suspension, so the same
 * length is added back here: a Suspended Account is never closer to deletion
 * than it was when it was suspended.
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
        $suspendedAt = $account->suspended_at;

        $account->forceFill([
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        if ($suspendedAt !== null && filled($account->deletion_scheduled_at)) {
            $frozenSeconds = max(0, (int) $suspendedAt->diffInSeconds(now()));

            $account->deletion_scheduled_at = $account->deletion_scheduled_at
                ->copy()
                ->addSeconds($frozenSeconds);
        }

        $account->save();

        RecordAuditEntry::execute($actor, 'account.lift', $account, $before, $this->snapshot($account));

        SyncDeletionClock::execute($account->fresh());

        return $account->fresh();
    }

    /**
     * @return array{suspended_at: string|null, suspension_reason: string|null, deletion_scheduled_at: string|null}
     */
    private function snapshot(Account $account): array
    {
        return [
            'suspended_at' => $account->suspended_at?->toIso8601String(),
            'suspension_reason' => $account->suspension_reason,
            'deletion_scheduled_at' => $account->deletion_scheduled_at?->toIso8601String(),
        ];
    }
}
