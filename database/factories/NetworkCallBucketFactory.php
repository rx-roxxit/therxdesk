<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NetworkCallBucket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkCallBucket>
 */
class NetworkCallBucketFactory extends Factory
{
    protected $model = NetworkCallBucket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'network' => 'tiktok',
            'date' => CarbonImmutable::now('UTC')->toDateString(),
            'calls' => 0,
            'exhausted_at' => null,
        ];
    }
}
