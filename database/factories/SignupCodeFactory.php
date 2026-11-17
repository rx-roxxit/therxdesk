<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\SignupCode\CreateSignupCode;
use App\Models\SignupCode;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SignupCode>
 */
class SignupCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => CreateSignupCode::generate(),
            'note' => null,
        ];
    }

    public function usedBy(User $user, ?DateTimeInterface $usedAt = null): static
    {
        return $this->state(fn (): array => [
            'used_by_user_id' => $user->id,
            'used_at' => $usedAt ?? now(),
        ]);
    }

    public function revoked(?DateTimeInterface $revokedAt = null): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => $revokedAt ?? now(),
        ]);
    }
}
