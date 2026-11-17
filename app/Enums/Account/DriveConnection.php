<?php

declare(strict_types=1);

namespace App\Enums\Account;

/**
 * Where the Owner's Google Drive sign-in for the Video policy's Backup stands.
 */
enum DriveConnection: string
{
    case None = 'none';
    case Connected = 'connected';
    case NeedsReconnect = 'needs_reconnect';
}
