<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Actions\VideoPolicy\ConnectDriveBackup;
use App\Actions\VideoPolicy\DisconnectDriveBackup;
use App\Enums\Media\Source;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Media\Sources\GoogleMediaOAuth;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * The Owner's Google Drive sign-in for the Video policy's Backup: full-page
 * redirect to Google and back, offline, `drive.file` only. The refresh token
 * goes straight into the encrypted connection; nothing reaches a URL.
 */
class DriveBackupController extends Controller
{
    private const string OAUTH_SESSION_KEY = 'drive_backup_oauth';

    private const int PKCE_TTL_SECONDS = 600;

    public function __construct(private readonly GoogleMediaOAuth $oauth) {}

    public function connect(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user()->account);

        if (! Source::GoogleDriveBackup->isEnabled()) {
            return $this->back('flash.error', __('rxdesk.drive_backup.errors.not_configured'));
        }

        $state = Str::random(40);
        $verifier = Str::random(96);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        Cache::put($this->verifierKey($request->user(), $state), Crypt::encryptString($verifier), self::PKCE_TTL_SECONDS);
        $request->session()->put(self::OAUTH_SESSION_KEY, ['state' => $state, 'user_id' => $request->user()->id]);

        return redirect()->away($this->oauth->authorizeOfflineUrl($state, $challenge));
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->authorize('update', $user->account);

        $pending = $request->session()->pull(self::OAUTH_SESSION_KEY);
        $state = $request->string('state')->toString();
        $code = $request->string('code')->toString();
        $expectedState = (string) data_get($pending, 'state');

        if (blank($expectedState)) {
            return $this->back('flash.error', __('rxdesk.drive_backup.errors.connect_failed'));
        }

        if (blank($state) || ! hash_equals($expectedState, $state)) {
            Cache::forget($this->verifierKey($user, $expectedState));

            return $this->back('flash.error', __('rxdesk.drive_backup.errors.connect_failed'));
        }

        if (data_get($pending, 'user_id') !== $user->id) {
            Cache::forget($this->verifierKey($user, $expectedState));

            return $this->back('flash.error', __('rxdesk.drive_backup.errors.connect_failed'));
        }

        $verifier = $this->pullVerifier($user, $state);

        if ($request->query('error') === 'access_denied') {
            return $this->back('flash.error', __('rxdesk.drive_backup.errors.scope_not_granted'));
        }

        if (blank($code)) {
            return $this->back('flash.error', __('rxdesk.drive_backup.errors.connect_failed'));
        }

        if (blank($verifier)) {
            return $this->back('flash.error', __('rxdesk.drive_backup.errors.connect_failed'));
        }

        try {
            $token = $this->oauth->exchangeCode($code, $verifier, (string) config('services.google_media.backup_redirect'));
        } catch (Throwable) {
            return $this->back('flash.error', __('rxdesk.drive_backup.errors.connect_failed'));
        }

        if (! collect(data_get($token, 'scopes'))->contains(GoogleMediaOAuth::scope(Source::GoogleDrive))) {
            return $this->back('flash.error', __('rxdesk.drive_backup.errors.scope_not_granted'));
        }

        $refreshToken = data_get($token, 'refresh_token');

        if (blank($refreshToken)) {
            return $this->back('flash.error', __('rxdesk.drive_backup.errors.connect_failed'));
        }

        ConnectDriveBackup::execute($user, (string) data_get($token, 'access_token'), $refreshToken, (int) data_get($token, 'expires_in'));

        return $this->back('flash.success', __('rxdesk.drive_backup.connected'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorize('update', $request->user()->account);

        DisconnectDriveBackup::execute($request->user()->account);

        return back();
    }

    private function back(string $flashKey, string $message): RedirectResponse
    {
        return redirect()->route('app.settings.preferences')->with($flashKey, $message);
    }

    private function verifierKey(User $user, string $state): string
    {
        $stateHash = hash('sha256', $state);

        return "drive-backup-pkce:{$user->id}:{$stateHash}";
    }

    private function pullVerifier(User $user, string $state): ?string
    {
        $encrypted = Cache::pull($this->verifierKey($user, $state));

        if (! is_string($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }
}
