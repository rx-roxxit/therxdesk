<?php

declare(strict_types=1);

namespace App\Enums\VideoRecord;

/**
 * What the Video policy did with one video of a published post.
 */
enum Outcome: string
{
    case Pending = 'pending';
    case Deleted = 'deleted';
    case BackedUp = 'backed_up';
    case BackupFailed = 'backup_failed';
}
