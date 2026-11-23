<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Actions\Account\DeleteAccountData;
use App\Models\Account;
use App\Models\Invite;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes one Account's data. A never-paid signup goes entirely (User
 * included); any other Account keeps its Owner, the empty Account and the
 * Stripe customer. `--dry-run` reports the impact and changes nothing. Off
 * while the app is self-hosted.
 */
class DeleteCommand extends Command
{
    protected $signature = 'rxdesk:delete
        {--account= : The Account UUID to delete}
        {--dry-run : Report the impact, delete nothing}';

    protected $description = "Delete an Account's data, keeping the Owner and Stripe customer unless never paid";

    public function handle(): int
    {
        if (config('trypost.self_hosted')) {
            $this->error('Self-hosted: account deletion is off.');

            return self::FAILURE;
        }

        $id = (string) $this->option('account');

        if (blank($id)) {
            $this->error('--account=<uuid> is required.');

            return self::FAILURE;
        }

        $account = Account::query()->with('owner')->find($id);

        if (! $account instanceof Account) {
            $this->error("No Account with id {$id}.");

            return self::FAILURE;
        }

        if (filled($account->data_purged_at)) {
            $this->info("Account {$id} was already purged at {$account->data_purged_at->toIso8601String()}.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->report($account);

            return self::SUCCESS;
        }

        $neverPaid = $account->isNeverPaid();

        DeleteAccountData::execute($account);

        $this->info($neverPaid
            ? "Deleted never-paid Account {$id} entirely."
            : "Purged Account {$id} data; Owner, empty Account and Stripe customer kept.");

        return self::SUCCESS;
    }

    private function report(Account $account): void
    {
        $workspaceIds = $account->workspaces()->pluck('id');

        $posts = Post::query()->whereIn('workspace_id', $workspaceIds)->count();

        $mediaQuery = Media::query()
            ->where(fn (Builder $query): Builder => $query
                ->whereIn('workspace_id', $workspaceIds)
                ->orWhereIn('post_id', Post::query()->select('id')->whereIn('workspace_id', $workspaceIds)));

        $this->table(['Account', 'Kind', 'Workspaces', 'Posts', 'Media', 'Members', 'Invites'], [[
            $account->id,
            $account->isNeverPaid() ? 'never paid (removed entirely)' : 'customer (Owner kept)',
            $workspaceIds->count(),
            $posts,
            $mediaQuery->count(),
            User::query()->where('account_id', $account->id)->count(),
            Invite::query()->where('account_id', $account->id)->count(),
        ]]);

        $this->info('Dry run: nothing was deleted.');
    }
}
