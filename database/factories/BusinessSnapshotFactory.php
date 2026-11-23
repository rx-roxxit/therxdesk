<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\BusinessStatus;
use App\Models\Account;
use App\Models\BusinessSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessSnapshot>
 */
class BusinessSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now('UTC')->toDateString(),
            'account_id' => Account::factory(),
            'plan_id' => null,
            'interval' => null,
            'mrr_cents' => 0,
            'status' => BusinessStatus::Lapsed,
            'offer_id' => null,
            'cancellation_type' => null,
            'cancellation_reason' => null,
        ];
    }
}
