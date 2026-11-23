<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\Account;

/**
 * Starts, clears or freezes the stored deletion clock. The clock is decided
 * here, once, and stored on `accounts.deletion_scheduled_at`: nothing reads
 * the subscription's state to work a deletion date out again.
 *
 * - An Account with app access has no clock.
 * - A first charge still being retried is not a lapse yet, so no clock.
 * - A Suspended Account keeps its clock as it is; lifting adds the frozen time
 *   back (see LiftSuspension).
 * - A never-paid signup is due 30 days after signup, a customer 30 days after
 *   access stops.
 */
class SyncDeletionClock
{
    public static function execute(Account $account): void
    {
        if (config('trypost.self_hosted')) {
            return;
        }

        if ($account->isSuspended() || filled($account->data_purged_at)) {
            return;
        }

        if ($account->hasAppAccess()) {
            self::clear($account);

            return;
        }

        if ($account->hasPendingFirstCharge()) {
            return;
        }

        if (filled($account->deletion_scheduled_at)) {
            return;
        }

        $dueAt = $account->isNeverPaid()
            ? $account->created_at->copy()->addDays(Account::DELETION_GRACE_DAYS)
            : now()->addDays(Account::DELETION_GRACE_DAYS);

        $account->forceFill(['deletion_scheduled_at' => $dueAt])->save();
    }

    private static function clear(Account $account): void
    {
        if (blank($account->deletion_scheduled_at)) {
            return;
        }

        $account->forceFill(['deletion_scheduled_at' => null])->save();
    }
}
