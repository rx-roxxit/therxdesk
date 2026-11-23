<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\Account;

/**
 * The one way the deletion clock's data removal runs. A never-paid signup is
 * removed entirely, User included; any other Account keeps its Owner, empty
 * Account and Stripe customer.
 */
class DeleteAccountData
{
    public static function execute(Account $account): void
    {
        if ($account->isNeverPaid()) {
            PurgeNeverPaidAccount::execute($account);

            return;
        }

        PurgeAccountData::execute($account);
    }
}
