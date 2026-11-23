<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\User\ReassignCurrentWorkspace;
use App\Actions\User\SettleStrandedMember;
use App\Actions\User\StrandedSettlement;
use App\Actions\Workspace\PurgeWorkspace;
use App\Models\Account;
use App\Models\Invite;
use App\Models\SupportConversation;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Deletes everything an Account owns but keeps the Owner's User, the empty
 * Account and the Stripe customer, so the Owner can sign in and subscribe
 * again later. Workspaces go through PurgeWorkspace, which removes their
 * media rows and files (R2 included) after the transaction commits.
 */
class PurgeAccountData
{
    public static function execute(Account $account): void
    {
        $settlement = DB::transaction(function () use ($account): StrandedSettlement {
            $locked = Account::query()->whereKey($account->id)->lockForUpdate()->first();

            if (! $locked || filled($locked->data_purged_at)) {
                return StrandedSettlement::none();
            }

            $owner = $locked->owner;

            Workspace::query()
                ->where('account_id', $locked->id)
                ->get()
                ->each(function (Workspace $workspace) use ($owner): void {
                    ReassignCurrentWorkspace::awayFromWorkspace(
                        $workspace,
                        exceptUserId: $owner?->id,
                    );

                    PurgeWorkspace::execute($workspace);
                });

            if ($owner !== null) {
                $owner->workspaces()->detach();
                $owner->update(['current_workspace_id' => null]);
            }

            Invite::query()->where('account_id', $locked->id)->delete();

            SupportConversation::query()->where('account_id', $locked->id)->delete();

            // Every non-owner member is deleted with the data; the Owner stays.
            $settlement = SettleStrandedMember::forAccountMembers($locked, exceptUserId: $owner?->id);

            $locked->forceFill([
                'deletion_scheduled_at' => null,
                'data_purged_at' => now(),
            ])->save();

            return $settlement;
        });

        $settlement->flush();
    }
}
