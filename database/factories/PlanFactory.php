<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(),
            'name' => 'Plan',
            'quota_bytes' => null,
            'ai_credits_per_month' => null,
            'workspace_limit' => 1,
            'sort' => 1,
            'is_archived' => false,
            'stripe_product_id' => null,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(fn (array $attributes): array => [
            'workspace_limit' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_archived' => true,
        ]);
    }
}
