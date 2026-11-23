<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Validation\ValidationException;

/**
 * The scheduling and publishing check for a network the System admin switched
 * off. Drafts are allowed; scheduling, publishing and republishing are not.
 */
class PlatformAvailability
{
    public static function violation(Platform|SocialAccount|null $target): ?string
    {
        $platform = $target instanceof SocialAccount ? $target->platform : $target;

        if ($platform === null || $platform->isAvailable()) {
            return null;
        }

        return __('rxdesk.availability.schedule_refused', ['network' => $platform->label()]);
    }

    public static function assertPublishable(Post $post): void
    {
        $violation = self::violation($post->platform);

        if ($violation !== null) {
            throw ValidationException::withMessages(['social_account_id' => $violation]);
        }
    }
}
