<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Alerts\AdminAlertKey;
use App\Models\AdminAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAlert>
 */
class AdminAlertFactory extends Factory
{
    protected $model = AdminAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => AdminAlertKey::CapacityRam->value,
            'is_open' => false,
            'opened_at' => null,
            'last_raised_at' => null,
            'last_notified_at' => null,
            'resolved_at' => null,
            'context' => [],
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => true,
            'opened_at' => now(),
            'last_raised_at' => now(),
            'resolved_at' => null,
        ]);
    }
}
