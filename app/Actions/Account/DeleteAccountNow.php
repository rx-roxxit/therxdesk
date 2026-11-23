<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Audit\RecordAuditEntry;
use App\Models\Account;
use Illuminate\Validation\ValidationException;

/**
 * The System admin's "Delete now": the Owner's email must be typed exactly,
 * the action is audited, then the data goes at once. The same outcome the
 * clock's 30 days produce.
 */
class DeleteAccountNow
{
    /**
     * @return string `never_paid` when the whole signup went, `purged` when
     *                the Owner, Account and Stripe customer were kept
     */
    public function execute(Account $account, string $confirmEmail, string $actor): string
    {
        $owner = $account->owner;

        if ($owner === null || trim($confirmEmail) !== $owner->email) {
            throw ValidationException::withMessages([
                'confirm_email' => "The email does not match the Owner's.",
            ]);
        }

        $before = [
            'deletion_scheduled_at' => $account->deletion_scheduled_at?->toIso8601String(),
            'data_purged_at' => $account->data_purged_at?->toIso8601String(),
            'never_paid' => $account->isNeverPaid(),
        ];

        $kind = $account->isNeverPaid() ? 'never_paid' : 'purged';

        DeleteAccountData::execute($account);

        RecordAuditEntry::execute($actor, 'account.delete_now', $account, $before, [
            'result' => $kind,
        ]);

        return $kind;
    }
}
