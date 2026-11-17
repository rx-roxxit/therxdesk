<?php

declare(strict_types=1);

namespace App\Observers;

use App\Exceptions\Storage\StorageRefused;
use App\Models\Media;
use App\Services\Storage\AccountStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Every path into storage (upload, import, per-channel copy) ends in a new
 * media row, so this is where the Quota is enforced for good. A refused row
 * is never written, and the file stored for it goes too.
 */
class MediaStorageObserver
{
    public function __construct(private readonly AccountStorage $storage) {}

    /**
     * @throws StorageRefused
     */
    public function creating(Media $media): void
    {
        try {
            $this->storage->ensureCanAdd($media, Auth::user());
        } catch (StorageRefused $refused) {
            $this->discardFile($media);

            throw $refused;
        }
    }

    private function discardFile(Media $media): void
    {
        if (blank($media->path) || Media::query()->where('path', $media->path)->exists()) {
            return;
        }

        Storage::delete($media->path);
    }
}
