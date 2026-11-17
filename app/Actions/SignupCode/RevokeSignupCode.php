<?php

declare(strict_types=1);

namespace App\Actions\SignupCode;

use App\Models\SignupCode;
use Illuminate\Support\Facades\DB;

class RevokeSignupCode
{
    /**
     * Revokes the code if nobody has used it yet, under the same row lock a
     * signup takes, so a code is never both used and revoked. Returns whether
     * it was revoked.
     */
    public static function execute(SignupCode $signupCode): bool
    {
        return DB::transaction(function () use ($signupCode): bool {
            $locked = SignupCode::query()->lockForUpdate()->findOrFail($signupCode->id);

            if (! $locked->isUnused()) {
                return false;
            }

            $locked->forceFill(['revoked_at' => now()])->save();

            return true;
        });
    }
}
