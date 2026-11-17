<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Enums\Account\DriveConnection;
use App\Enums\Account\VideoPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Settings\UpdatePreferencesRequest;
use App\Models\User;
use App\Support\Timezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class PreferencesController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile/Preferences', [
            'timezones' => Timezone::options(),
            'videoPolicy' => $this->videoPolicyFor($request->user()),
            'driveConnection' => $this->driveConnectionFor($request->user()),
        ]);
    }

    /**
     * Only the account's Owner sees (and changes) the Video policy.
     */
    private function videoPolicyFor(User $user): ?VideoPolicy
    {
        $account = $user->account;

        if ($account === null || $user->cannot('update', $account)) {
            return null;
        }

        return $account->video_policy;
    }

    /**
     * Only the account's Owner sees the Google Drive connection.
     */
    private function driveConnectionFor(User $user): ?DriveConnection
    {
        $account = $user->account;

        if ($account === null || $user->cannot('update', $account)) {
            return null;
        }

        return $account->driveConnection();
    }

    public function update(UpdatePreferencesRequest $request): RedirectResponse|HttpResponse
    {
        $request->user()->update($request->validated());

        return $request->wantsJson() ? response()->noContent() : back();
    }
}
