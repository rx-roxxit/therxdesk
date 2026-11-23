<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportConversation>
 */
class SupportConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'last_page' => '/posts',
            'last_message_at' => now(),
        ];
    }

    /**
     * A conversation the chat no longer resumes by itself.
     */
    public function idle(): static
    {
        return $this->state(fn (): array => [
            'last_message_at' => now()->subHours(SupportConversation::RESUME_HOURS + 1),
        ]);
    }
}
