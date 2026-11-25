<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Actions\Account\DeleteAccountData;
use App\Actions\Account\SendLifecycleEmails;
use App\Actions\Account\SyncDeletionClock;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * The daily lifecycle sweep: keep the deletion clock true, send the emails
 * that are due, then remove the data whose 30 days have passed. Nothing runs
 * while the app is self-hosted.
 */
class LifecycleCommand extends Command
{
    protected $signature = 'rxdesk:lifecycle
        {--account= : Only this Account UUID}';

    protected $description = 'Send lifecycle emails and delete Accounts past their deletion date';

    public function handle(): int
    {
        if (config('trypost.self_hosted')) {
            $this->info('Self-hosted: lifecycle emails and deletion are off.');

            return self::SUCCESS;
        }

        $query = Account::query()
            ->whereNotNull('owner_id')
            ->with(['owner', 'plan.prices', 'subscriptions']);

        if (filled($this->option('account'))) {
            $query->whereKey((string) $this->option('account'));
        }

        $query->chunkById(200, function (Collection $accounts): void {
            foreach ($accounts as $account) {
                SyncDeletionClock::execute($account);

                $account->refresh();

                SendLifecycleEmails::execute($account);
                $this->purgeIfDue($account);
            }
        });

        $this->info('Lifecycle sweep finished.');

        return self::SUCCESS;
    }

    private function purgeIfDue(Account $account): void
    {
        if (! $account->canBePurged()) {
            return;
        }

        $neverPaid = $account->isNeverPaid();

        if (! DeleteAccountData::executeIfLapsed($account)) {
            return;
        }

        $this->line($neverPaid
            ? "Deleted never-paid Account {$account->id} entirely."
            : "Purged data of Account {$account->id}; Owner and Stripe customer kept.");
    }
}
