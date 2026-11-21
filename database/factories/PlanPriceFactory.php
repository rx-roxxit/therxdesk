<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\Interval;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'interval' => Interval::Monthly,
            'amount' => 1190,
            'stripe_price_id' => null,
            'is_active' => true,
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'interval' => Interval::Yearly,
            'amount' => 11900,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
