<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Models\RestoreDrill;
use App\Services\Ops\RestoreDrillStatus;
use Illuminate\Console\Command;

/**
 * Records that a restore drill was performed and prints when the next one is
 * due.
 */
class RecordRestoreDrillCommand extends Command
{
    protected $signature = 'rxdesk:restore-drill';

    protected $description = 'Record a restore drill and print when the next one is due';

    public function handle(RestoreDrillStatus $status): int
    {
        RestoreDrill::query()->create([
            'performed_at' => now(),
        ]);

        $dueAt = $status->dueAt();

        if ($dueAt === null) {
            $this->warn('The next restore drill is due now.');

            return self::SUCCESS;
        }

        $this->info("The next restore drill is due on {$dueAt->toIso8601String()}.");

        return self::SUCCESS;
    }
}
