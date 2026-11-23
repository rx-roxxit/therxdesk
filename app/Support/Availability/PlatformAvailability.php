<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Enums\SocialAccount\Platform;
use App\Models\Account;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Validation\ValidationException;

/**
 * The scheduling and publishing check for a network the System admin switched
 * off. Drafts are allowed; scheduling, publishing and republishing are not.
 * An Account with Review access may still use a network that is installed but
 * switched off.
 */
class PlatformAvailability
{
    public static function violation(Platform|SocialAccount|null $target, ?Account $account = null): ?string
    {
        $platform = $target instanceof SocialAccount ? $target->platform : $target;

        if ($account === null && $target instanceof SocialAccount) {
            $account = $target->loadMissing('workspace.account')->workspace?->account;
        }

        if ($platform === null || $platform->isAvailableFor($account)) {
            return null;
        }

        return __('rxdesk.availability.schedule_refused', ['network' => $platform->label()]);
    }

    public static function assertPublishable(Post $post): void
    {
        $post->loadMissing('workspace.account');

        $violation = self::violation($post->platform, $post->workspace?->account);

        if ($violation !== null) {
            throw ValidationException::withMessages(['social_account_id' => $violation]);
        }
    }
}
