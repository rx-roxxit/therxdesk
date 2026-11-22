<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Account\AccessDenial;
use App\Models\Post;

/**
 * The one check that stops a post of an Account without access from reaching
 * the network. PublishPost and PublishToSocialPlatform call it, so scheduling,
 * retries and limit retries pass through it alike.
 */
class PublishingGate
{
    /**
     * Availability (#63) checks the network's own switch here first, before the
     * Account's access, and returns its own AccessDenial case.
     */
    public static function denial(Post $post): ?AccessDenial
    {
        return $post->workspace?->account?->accessDenial();
    }

    public static function block(Post $post, AccessDenial $reason): void
    {
        $post->markPublicationBlocked($reason);

        app(FinalizePostPublication::class)->handle($post);
    }
}
