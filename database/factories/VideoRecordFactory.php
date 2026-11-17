<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VideoRecord\Outcome;
use App\Models\Post;
use App\Models\VideoRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VideoRecord>
 */
class VideoRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'media_id' => (string) Str::uuid(),
            'original_filename' => $this->faker->word().'.mp4',
            'size' => $this->faker->numberBetween(1_000_000, 50_000_000),
            'outcome' => Outcome::Deleted,
        ];
    }
}
