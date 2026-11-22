<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $this->verify($request);

        $redirect = $this->redirectToWelcome($request);

        if ($redirect !== null) {
            return $redirect;
        }

        return redirect()->intended(route('app.calendar').'?verified=1');
    }

    private function verify(EmailVerificationRequest $request): void
    {
        if ($request->user()->hasVerifiedEmail()) {
            return;
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }
    }

    /**
     * Selling mode holds an Owner without app access at the plan step (members
     * on their hold screen), so the verification link comes back there instead
     * of dropping them on the calendar without a subscription.
     */
    private function redirectToWelcome(EmailVerificationRequest $request): ?RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return null;
        }

        $user = $request->user();

        if ($user->account?->hasAppAccess()) {
            return null;
        }

        if ($user->isAccountOwner()) {
            return redirect()->route('app.welcome.plan');
        }

        return redirect()->route('app.welcome.subscription-required');
    }
}
