<?php

declare(strict_types=1);

namespace App\Enums\Account;

/**
 * What happens to a post's videos one hour after it publishes successfully,
 * chosen once per account by its Owner. Keep is upstream's behaviour.
 */
enum VideoPolicy: string
{
    case Keep = 'keep';
    case Delete = 'delete';
    case Backup = 'backup';

    /**
     * The policies an Owner may pick today. Backup needs the Google Drive
     * connection, which is not built yet.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Keep, self::Delete];
    }
}
