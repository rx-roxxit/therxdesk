<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\Invite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Carries the invite id across the OAuth round-trip via session, mirroring
 * PreservesAttributionParameters.
 */
trait PreservesInvite
{
    private function storeInvite(Request $request): void
    {
        $request->session()->put('oauth_invite_id', $request->string('invite')->toString());
    }

    private function retrieveInvite(): ?string
    {
        return session()->pull('oauth_invite_id');
    }

    private function inviteEmailMismatchRedirect(?Invite $invite, ?string $oauthEmail): ?RedirectResponse
    {
        if ($invite && ! $invite->isFor($oauthEmail)) {
            return redirect()->route('login')->withErrors([
                'email' => __('settings.members.flash.wrong_email'),
            ]);
        }

        return null;
    }
}
