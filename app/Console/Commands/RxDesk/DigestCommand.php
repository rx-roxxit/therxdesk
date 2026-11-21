<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Services\Alerts\AdminAlertDigest;
use App\Services\Alerts\AdminAlertNotifier;
use Illuminate\Console\Command;

/**
 * Sends the daily digest at 08:00 Asia/Bangkok, and stays quiet on a day with
 * nothing to report.
 */
class DigestCommand extends Command
{
    protected $signature = 'rxdesk:digest';

    protected $description = 'Email the daily admin digest when there is something to report';

    public function handle(AdminAlertDigest $digest, AdminAlertNotifier $notifier): int
    {
        if (config('trypost.self_hosted')) {
            return self::SUCCESS;
        }

        $items = $digest->items();

        if ($digest->isEmpty($items)) {
            return self::SUCCESS;
        }

        $notifier->sendDigest($items);

        return self::SUCCESS;
    }
}
