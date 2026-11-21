<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BackupRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupRun>
 */
class BackupRunFactory extends Factory
{
    protected $model = BackupRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'completed_at' => now(),
        ];
    }
}
