<?php

declare(strict_types=1);

namespace App\Actions\SignupCode;

use App\Actions\User\CreateUser;
use App\Models\SignupCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOwnerWithSignupCode
{
    /**
     * Creates the Owner (account and first workspace, as `CreateUser` does
     * for any signup without an invite) and uses up the code in the same
     * transaction. The code row is locked first, so of two signups racing
     * for one code the second waits, finds it used and creates nothing.
     *
     * @param  array{name: string, email: string, password?: string, registration_ip?: string|null, locale?: string|null, timezone?: string|null, week_starts_on?: string|null, time_format?: string|null}  $data
     * @param  array<string, string>  $attributionParameters
     *
     * @throws ValidationException when the code is wrong, used or revoked
     */
    public static function execute(string $code, array $data, array $attributionParameters = []): User
    {
        return DB::transaction(function () use ($code, $data, $attributionParameters): User {
            $signupCode = SignupCode::query()->withCode($code)->lockForUpdate()->first();
            $refusal = SignupCode::refusalFor($signupCode);

            if (filled($refusal)) {
                throw ValidationException::withMessages(['signup_code' => $refusal]);
            }

            $user = CreateUser::execute([...$data, 'is_invite' => false], $attributionParameters);

            $signupCode->forceFill([
                'used_by_user_id' => $user->id,
                'used_at' => now(),
            ])->save();

            return $user;
        });
    }
}
