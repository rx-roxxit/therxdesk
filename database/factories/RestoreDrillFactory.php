<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RestoreDrill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestoreDrill>
 */
class RestoreDrillFactory extends Factory
{
    protected $model = RestoreDrill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performed_at' => now(),
        ];
    }
}
