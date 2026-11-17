<?php

declare(strict_types=1);

namespace App\Services\Cover;

use App\Exceptions\Cover\CoverNotReady;
use App\Exceptions\Cover\CoverRefused;
use App\Models\Media;
use App\Models\Post;

/**
 * One network's way to give an already published video its Cover. A network
 * joins the Cover by adding one class that implements this and one entry in
 * `NetworkCovers`; the job, the outcome and the post details line are shared.
 */
interface NetworkCover
{
    /**
     * @throws CoverNotReady when the network is not ready yet, so the job tries again
     * @throws CoverRefused when the network will not take it, so the job gives up
     */
    public function set(Post $post, Media $cover): void;
}
