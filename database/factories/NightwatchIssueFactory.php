<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NightwatchIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NightwatchIssue>
 */
class NightwatchIssueFactory extends Factory
{
    protected $model = NightwatchIssue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => fake()->uuid(),
            'ref' => fake()->numberBetween(1, 10_000),
            'type' => 'exception',
            'title' => fake()->sentence(4),
            'status' => 'open',
            'priority' => 'high',
            'url' => fake()->url(),
            'environment' => 'production',
            'details' => [],
            'opened_at' => now(),
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }
}
