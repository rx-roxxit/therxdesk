<?php

declare(strict_types=1);

namespace App\Actions\VideoPolicy;

use App\Enums\Account\VideoPolicy;
use App\Models\Account;

class DisconnectDriveBackup
{
    /**
     * Forget the Owner's Drive sign-in. A policy of Backup cannot work
     * without it, so it goes back to Keep.
     */
    public static function execute(Account $account): void
    {
        $account->driveBackupConnection()?->delete();

        if ($account->video_policy !== VideoPolicy::Backup) {
            return;
        }

        $account->forceFill(['video_policy' => VideoPolicy::Keep])->save();
    }
}
