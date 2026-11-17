<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Dto\MediaItem;
use App\Services\Social\InstagramPublisher;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * A public URL of a video's Cover image as a JPEG, for a network that takes
 * nothing else (Instagram's `cover_url`). A JPEG is used as stored; a PNG is
 * converted once into the publishers' derivative folder, which
 * `PruneTemporaryUploads` empties after a few days. The stored Cover is never
 * changed.
 */
class CoverImageJpeg
{
    private const int QUALITY = 90;

    public function url(MediaItem $cover): string
    {
        if ($cover->mime_type === 'image/jpeg') {
            return $cover->url;
        }

        $image = (new ImageManager(Driver::class))->decodeBinary((string) Storage::get($cover->path));
        $path = InstagramPublisher::CROP_DIRECTORY.'/'.Str::uuid()->toString().'.jpg';
        Storage::put($path, (string) $image->encodeUsingMediaType('image/jpeg', quality: self::QUALITY));

        return Storage::url($path);
    }
}
