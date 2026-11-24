<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NetworkLimitSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkLimitSetting>
 */
class NetworkLimitSettingFactory extends Factory
{
    protected $model = NetworkLimitSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => NetworkLimitSetting::SINGLETON_ID,
            ...NetworkLimitSetting::DEFAULTS,
        ];
    }
}
