<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

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

    /**
     * The lifecycle sweep's removal. The sweep's earlier checks run outside any
     * lock, so the Account is re-read under its row lock: a payment or trial
     * that landed meanwhile keeps the data, and the next sweep sees the new
     * state.
     */
    public static function executeIfLapsed(Account $account): bool
    {
        return DB::transaction(function () use ($account): bool {
            $locked = Account::query()->whereKey($account->id)->lockForUpdate()->first();

            if (! $locked instanceof Account || ! $locked->canBePurged()) {
                return false;
            }

            self::execute($locked);

            return true;
        });
    }
}
