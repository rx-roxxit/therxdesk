<?php

declare(strict_types=1);

namespace App\Actions\VideoPolicy;

use App\Models\Account;

class MarkDriveNeedsReconnect
{
    /**
     * Google rejected the Owner's Drive token: flag the connection so
     * Preferences asks for a new sign-in and Backup cannot be picked until
     * then. An account with no connection is left alone.
     */
    public static function execute(Account $account): void
    {
        $connection = $account->driveBackupConnection();

        if (blank($connection)) {
            return;
        }

        $connection->forceFill(['needs_reconnect_at' => now()])->save();
    }
}
