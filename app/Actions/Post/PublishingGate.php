<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Account\AccessDenial;
use App\Models\Post;

/**
 * The one check that stops a post from reaching the network: the platform's
 * Availability switch first, then the Account's access. PublishPost and
 * PublishToSocialPlatform call it, so scheduling, retries and limit retries
 * pass through it alike.
 */
class PublishingGate
{
    /**
     * Availability (#63) checks the network's own switch here first, before the
     * Account's access. An Account with Review access may publish to a network
     * that is installed but switched off. A post without a platform falls
     * through.
     */
    public static function denial(Post $post): ?AccessDenial
    {
        $account = $post->workspace?->account;

        if ($post->platform !== null && ! $post->platform->isAvailableFor($account)) {
            return AccessDenial::Unavailable;
        }

        return $account?->accessDenial();
    }

    public static function block(Post $post, AccessDenial $reason): void
    {
        $post->markPublicationBlocked($reason);

        app(FinalizePostPublication::class)->handle($post);
    }
}
