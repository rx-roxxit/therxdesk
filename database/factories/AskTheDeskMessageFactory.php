<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AskTheDesk\AskTheDeskRole;
use App\Models\AskTheDeskMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AskTheDeskMessage>
 */
class AskTheDeskMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => AskTheDeskRole::User,
            'content' => fake()->sentence(),
        ];
    }

    public function answer(): static
    {
        return $this->state(fn (): array => [
            'role' => AskTheDeskRole::Assistant,
            'content' => fake()->paragraph(),
        ]);
    }
}
