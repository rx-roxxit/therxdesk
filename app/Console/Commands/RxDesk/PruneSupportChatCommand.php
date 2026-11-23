<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Models\SupportConversation;
use Illuminate\Console\Command;

class PruneSupportChatCommand extends Command
{
    protected $signature = 'rxdesk:prune-support-chat';

    protected $description = 'Delete Support chat conversations older than 30 days.';

    public function handle(): int
    {
        $deleted = SupportConversation::query()
            ->expired(now()->subDays(SupportConversation::RETENTION_DAYS))
            ->delete();

        $this->info("Deleted {$deleted} Support chat conversations.");

        return self::SUCCESS;
    }
}
