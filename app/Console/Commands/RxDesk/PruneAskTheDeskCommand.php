<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Models\AskTheDeskMessage;
use Illuminate\Console\Command;

class PruneAskTheDeskCommand extends Command
{
    protected $signature = 'rxdesk:prune-ask-the-desk';

    protected $description = 'Delete Ask the Desk messages older than 30 days.';

    public function handle(): int
    {
        $deleted = AskTheDeskMessage::query()
            ->where('created_at', '<', now()->subDays(AskTheDeskMessage::RETENTION_DAYS))
            ->delete();

        $this->info("Deleted {$deleted} Ask the Desk messages.");

        return self::SUCCESS;
    }
}
