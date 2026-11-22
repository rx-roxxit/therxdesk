<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Billing\Interval;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Seeder;

/**
 * The whole plan catalog: four plans that differ only in Quota and AI credits.
 * Running it again keeps the catalog as defined here; a price change adds a row
 * and deactivates the old one, and a plan that left the catalog is archived,
 * never deleted.
 */
class PlanSeeder extends Seeder
{
    /**
     * GB is 1024^3, prices are USD cents, and a year always costs ten months.
     *
     * @var array<int, array{slug: string, name: string, quota_bytes: int, ai_credits_per_month: int, sort: int, monthly: int}>
     */
    private const PLANS = [
        ['slug' => 'mini', 'name' => 'Mini', 'quota_bytes' => 2147483648, 'ai_credits_per_month' => 2500, 'sort' => 1, 'monthly' => 1190],
        ['slug' => 'standard', 'name' => 'Standard', 'quota_bytes' => 5368709120, 'ai_credits_per_month' => 10000, 'sort' => 2, 'monthly' => 1790],
        ['slug' => 'plus', 'name' => 'Plus', 'quota_bytes' => 21474836480, 'ai_credits_per_month' => 25000, 'sort' => 3, 'monthly' => 2990],
        ['slug' => 'max', 'name' => 'Max', 'quota_bytes' => 53687091200, 'ai_credits_per_month' => 40000, 'sort' => 4, 'monthly' => 4990],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $definition) {
            $plan = Plan::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'quota_bytes' => $definition['quota_bytes'],
                    'ai_credits_per_month' => $definition['ai_credits_per_month'],
                    'workspace_limit' => null,
                    'sort' => $definition['sort'],
                    'is_archived' => false,
                ],
            );

            $this->seedPrice($plan, Interval::Monthly, $definition['monthly']);
            $this->seedPrice($plan, Interval::Yearly, $definition['monthly'] * 10);
        }

        Plan::query()
            ->whereNotIn('slug', array_column(self::PLANS, 'slug'))
            ->update(['is_archived' => true]);
    }

    private function seedPrice(Plan $plan, Interval $interval, int $amount): void
    {
        $active = $plan->prices()
            ->where('interval', $interval)
            ->where('is_active', true)
            ->latest()
            ->first();

        if ($active?->amount === $amount) {
            return;
        }

        if ($active !== null) {
            $active->update(['is_active' => false]);
        }

        PlanPrice::create([
            'plan_id' => $plan->id,
            'interval' => $interval,
            'amount' => $amount,
            'is_active' => true,
        ]);
    }
}
