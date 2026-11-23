<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Support\SupportFeedback;
use App\Enums\Support\SupportMessageRole;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportMessage>
 */
class SupportMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => SupportConversation::factory(),
            'role' => SupportMessageRole::User,
            'content' => fake()->sentence(),
            'feedback' => null,
        ];
    }

    public function answer(): static
    {
        return $this->state(fn (): array => [
            'role' => SupportMessageRole::Assistant,
            'content' => fake()->paragraph(),
        ]);
    }

    public function feedback(SupportFeedback $feedback): static
    {
        return $this->state(fn (): array => ['feedback' => $feedback]);
    }
}
