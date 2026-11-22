<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Billing\OfferReach;
use App\Models\Offer;
use Illuminate\Database\Seeder;

/**
 * Local trial Offers to try the Welcome checkout with, run by hand with
 * `php artisan db:seed --class=OfferSeeder`. A discount Offer creates a Stripe
 * coupon, so it is created through the admin API in an environment with a
 * synced Catalog instead; these two grant days only.
 */
class OfferSeeder extends Seeder
{
    public function run(): void
    {
        Offer::updateOrCreate(
            ['slug' => 'welcome-trial'],
            [
                'name' => 'Welcome trial',
                'reach' => OfferReach::General,
                'trial_days' => 14,
                'starts_at' => now(),
                'show_banner' => false,
            ],
        );

        Offer::updateOrCreate(
            ['slug' => 'partner-trial'],
            [
                'name' => 'Partner trial',
                'reach' => OfferReach::Link,
                'trial_days' => 7,
                'starts_at' => now(),
                'show_banner' => true,
            ],
        );
    }
}
