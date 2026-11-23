<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\User\CreateUser;
use App\Enums\Availability\ServiceSwitch;
use App\Http\Controllers\Auth\Concerns\PreservesAttributionParameters;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Auth\RegisterRequest;
use App\Models\Invite;
use App\Support\Auth\PasswordRequirements;
use App\Support\Countries;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    use PreservesAttributionParameters;

    public function create(Request $request): Response
    {
        $this->storeAttributionParameters($request);

        $inviteId = $request->query('invite') ?? $request->session()->get('pending_invite_id');
        $invite = Invite::fromId(is_string($inviteId) ? $inviteId : null);
        $signupsClosed = ServiceSwitch::newSignupsClosed() && $invite === null;

        $turnstileSiteKey = config('rxdesk.turnstile.site_key');

        return Inertia::render('auth/Register', [
            'email' => $request->query('email'),
            'invite' => $signupsClosed ? null : $invite?->id,
            'signupsClosed' => $signupsClosed,
            'passwordRequirements' => PasswordRequirements::checklist(),
            'countries' => config('trypost.self_hosted') ? null : Countries::ISO,
            'turnstileSiteKey' => filled($turnstileSiteKey) ? $turnstileSiteKey : null,
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $attributionParameters = $this->retrieveAttributionParameters();
        $invite = $request->invite();

        $user = CreateUser::execute([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'is_invite' => $invite !== null,
            'registration_ip' => $request->ip(),
            'country' => $request->validated('country'),
            'locale' => $request->validated('locale'),
            'timezone' => $request->validated('timezone'),
            'week_starts_on' => $request->validated('week_starts_on'),
            'time_format' => $request->validated('time_format'),
        ], $attributionParameters);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->forget('pending_invite_id');

        if ($invite) {
            return redirect()->route('app.invites.show', $invite);
        }

        return redirect()->route('app.welcome');
    }
}
