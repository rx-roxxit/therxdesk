<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Services\Alerts\CapacityCheck;
use Illuminate\Console\Command;

/**
 * Runs every five minutes in selling mode: reads host RAM and load, the
 * Horizon wait and free disk, and raises or clears the matching alerts.
 */
class CapacityCommand extends Command
{
    protected $signature = 'rxdesk:capacity';

    protected $description = 'Check host, queue and disk capacity and raise admin alerts';

    public function handle(CapacityCheck $check): int
    {
        if (config('trypost.self_hosted')) {
            return self::SUCCESS;
        }

        $check->run();

        return self::SUCCESS;
    }
}
