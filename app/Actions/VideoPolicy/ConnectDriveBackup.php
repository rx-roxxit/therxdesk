<?php

declare(strict_types=1);

namespace App\Actions\VideoPolicy;

use App\Enums\Media\Source;
use App\Models\MediaSourceConnection;
use App\Models\User;

class ConnectDriveBackup
{
    /**
     * Remember the Owner's Google Drive sign-in for Backup, replacing an
     * earlier one and clearing its "needs reconnect" mark. It lives under its
     * own source, apart from the composer picker.
     */
    public static function execute(User $owner, string $accessToken, string $refreshToken, int $expiresIn): MediaSourceConnection
    {
        return MediaSourceConnection::query()->updateOrCreate(
            ['user_id' => $owner->id, 'source' => Source::GoogleDriveBackup],
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addSeconds($expiresIn),
                'needs_reconnect_at' => null,
            ],
        );
    }
}
