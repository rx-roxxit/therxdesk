<?php

declare(strict_types=1);

namespace App\Enums\Storage;

use App\Services\Storage\StorageUsage;
use App\Support\ByteSize;

/**
 * Why `AccountStorage` refuses to store more bytes for an account. The Disk
 * guard is checked first, so its "server is out of space" wins over the Quota.
 */
enum StorageRefusal: string
{
    case DiskGuard = 'disk_guard';
    case Quota = 'quota';

    /**
     * The validation message: the Owner hears it is their storage, anyone else
     * that it is the workspace owner's.
     */
    public function message(StorageUsage $usage, bool $toOwner): string
    {
        return match ($this) {
            self::DiskGuard => __('rxdesk.storage.errors.disk_guard'),
            self::Quota => $this->quotaMessage($usage, $toOwner),
        };
    }

    /**
     * The reason a queued import records (`MediaImportStatus`); the composer
     * maps it to its message.
     */
    public function importFailure(bool $toOwner): string
    {
        return match ($this) {
            self::DiskGuard => 'server_out_of_space',
            self::Quota => $toOwner ? 'storage_full' : 'owner_storage_full',
        };
    }

    private function quotaMessage(StorageUsage $usage, bool $toOwner): string
    {
        if ($toOwner) {
            return __('rxdesk.storage.errors.quota_owner', [
                'used' => ByteSize::format($usage->used),
                'limit' => ByteSize::format($usage->limit),
            ]);
        }

        return __('rxdesk.storage.errors.quota_member');
    }
}
