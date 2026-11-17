<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Cover\Outcome;
use App\Models\CoverOutcome;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverOutcome>
 */
class CoverOutcomeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'outcome' => Outcome::Failed,
        ];
    }
}
