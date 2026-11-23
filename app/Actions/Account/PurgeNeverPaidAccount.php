<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\User\PurgeUserAccess;
use App\Actions\User\ReassignCurrentWorkspace;
use App\Actions\User\StrandedSettlement;
use App\Actions\Workspace\PurgeWorkspace;
use App\Models\Account;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;

/**
 * Deletes a signup that never had a Subscription entirely: its workspaces,
 * media (R2 included), channels, Invites, Members, Users and the Account
 * itself. No email is sent.
 */
class PurgeNeverPaidAccount
{
    public static function execute(Account $account): void
    {
        [$settlement, $stripeId] = DB::transaction(function () use ($account): array {
            $locked = Account::query()->whereKey($account->id)->lockForUpdate()->first();

            if (! $locked) {
                return [StrandedSettlement::none(), null];
            }

            $users = $locked->users()->get();

            Workspace::query()
                ->where('account_id', $locked->id)
                ->get()
                ->each(function (Workspace $workspace): void {
                    ReassignCurrentWorkspace::awayFromWorkspace($workspace);

                    PurgeWorkspace::execute($workspace);
                });

            Invite::query()->where('account_id', $locked->id)->delete();

            $settlement = StrandedSettlement::none();

            foreach ($users as $user) {
                $settlement = $settlement->merge(new StrandedSettlement(
                    mediaPaths: PurgeUserAccess::execute($user),
                ));

                $user->workspaces()->detach();
            }

            User::query()
                ->whereIn('id', $users->modelKeys())
                ->update(['account_id' => null, 'current_workspace_id' => null]);

            User::query()->whereIn('id', $users->modelKeys())->delete();

            $locked->subscriptions()->delete();

            $stripeId = $locked->stripe_id;

            $locked->forceFill(['owner_id' => null])->save();
            $locked->delete();

            return [$settlement, $stripeId];
        });

        $settlement->flush();

        if (filled($stripeId)) {
            rescue(
                fn () => Cashier::stripe()->customers->delete($stripeId),
                report: false,
            );
        }
    }
}
