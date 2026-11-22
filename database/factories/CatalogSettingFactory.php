<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CatalogSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogSetting>
 */
class CatalogSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return CatalogSetting::DEFAULTS;
    }
}
