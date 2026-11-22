<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\Interval;
use App\Enums\Billing\OfferDiscountType;
use App\Enums\Billing\OfferDuration;
use App\Enums\Billing\OfferReach;
use App\Models\Offer;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->words(2, true),
            'reach' => OfferReach::Link,
            'discount_type' => OfferDiscountType::Percent,
            'percent_off' => 20,
            'amount_off' => null,
            'duration' => OfferDuration::Once,
            'duration_in_months' => null,
            'stripe_coupon_id' => fake()->unique()->bothify('coupon_????????'),
            'trial_days' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'max_redemptions' => null,
            'show_banner' => false,
            'plan_ids' => null,
            'intervals' => [Interval::Monthly->value],
        ];
    }

    public function general(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reach' => OfferReach::General,
        ]);
    }

    public function link(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reach' => OfferReach::Link,
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->addDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function limitedTo(int $places): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_redemptions' => $places,
        ]);
    }

    /**
     * A trial-only Offer: no coupon, so it grants days instead of a discount.
     */
    public function trial(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => null,
            'percent_off' => null,
            'amount_off' => null,
            'duration' => null,
            'duration_in_months' => null,
            'stripe_coupon_id' => null,
            'trial_days' => $days,
        ]);
    }

    public function percent(float $percent): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => OfferDiscountType::Percent,
            'percent_off' => $percent,
            'amount_off' => null,
        ]);
    }

    /**
     * An amount-off coupon, in USD cents.
     */
    public function amount(int $cents): static
    {
        return $this->state(fn (array $attributes): array => [
            'discount_type' => OfferDiscountType::Amount,
            'percent_off' => null,
            'amount_off' => $cents,
        ]);
    }

    public function once(): static
    {
        return $this->state(fn (array $attributes): array => [
            'duration' => OfferDuration::Once,
            'duration_in_months' => null,
        ]);
    }

    public function repeating(int $months): static
    {
        return $this->state(fn (array $attributes): array => [
            'duration' => OfferDuration::Repeating,
            'duration_in_months' => $months,
        ]);
    }

    public function forever(): static
    {
        return $this->state(fn (array $attributes): array => [
            'duration' => OfferDuration::Forever,
            'duration_in_months' => null,
        ]);
    }

    /**
     * @param  list<Plan>|list<string>  $plans
     * @param  list<Interval|string>  $intervals
     */
    public function covering(array $plans, array $intervals = [Interval::Monthly]): static
    {
        return $this->state(fn (array $attributes): array => [
            'plan_ids' => array_map(fn (Plan|string $plan): string => $plan instanceof Plan ? (string) $plan->id : $plan, $plans),
            'intervals' => array_map(fn (Interval|string $interval): string => $interval instanceof Interval ? $interval->value : $interval, $intervals),
        ]);
    }

    public function withBanner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'show_banner' => true,
        ]);
    }

    /**
     * The Black Friday campaign: 50% off the first 3 months, with its banner.
     */
    public function blackFriday(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Black Friday',
            'percent_off' => 50,
            'duration' => OfferDuration::Repeating,
            'duration_in_months' => 3,
            'show_banner' => true,
        ]);
    }

    /**
     * A $5 first month, for an amount coupon.
     */
    public function firstMonth(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'First month',
            'discount_type' => OfferDiscountType::Amount,
            'percent_off' => null,
            'amount_off' => 500,
            'duration' => OfferDuration::Once,
        ]);
    }

    /**
     * A longer trial Offer, with no coupon.
     */
    public function freeTrial(int $days = 14): static
    {
        return $this->trial($days)->state(fn (array $attributes): array => [
            'name' => 'Free trial',
        ]);
    }
}
