<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountNetworkCall;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountNetworkCall>
 */
class AccountNetworkCallFactory extends Factory
{
    protected $model = AccountNetworkCall::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'network' => 'tiktok',
            'date' => CarbonImmutable::now('UTC')->toDateString(),
            'calls' => 0,
        ];
    }
}
