<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Enums\Billing\Interval;
use App\Exceptions\Catalog\StripeRejected;
use App\Models\Plan;
use App\Services\Catalog\Catalog;
use Database\Seeders\PlanSeeder;
use Illuminate\Console\Command;

/**
 * Converges every active Plan to the current Stripe key: Products and Prices
 * are created when missing and their ids written into `plans` and
 * `plan_prices`. Run it after a Stripe key change (test to live, or a new
 * Operator's account). Running it twice with the same key creates nothing.
 *
 * A first deploy has no Catalog at all, so an empty `plans` table is seeded
 * from the committed default first; `--if-needed` skips the Stripe calls when
 * every Plan already carries its ids, which is what the container runs on
 * every boot.
 */
class CatalogSyncCommand extends Command
{
    protected $signature = 'rxdesk:catalog-sync
        {--if-needed : Skip when every active Plan already has its Stripe ids}';

    protected $description = 'Create or update the Stripe Products and Prices of every active Plan';

    public function handle(Catalog $catalog): int
    {
        $this->seedEmptyCatalog();

        if ($this->option('if-needed') && $this->catalogIsComplete()) {
            $this->info('The Catalog already has its Stripe Products and Prices.');

            return self::SUCCESS;
        }

        try {
            $synced = $catalog->sync('cli');
        } catch (StripeRejected $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$synced} Plans.");

        return self::SUCCESS;
    }

    private function seedEmptyCatalog(): void
    {
        if (Plan::query()->exists()) {
            return;
        }

        $this->info('Seeding the default Catalog.');

        $this->call('db:seed', ['--class' => PlanSeeder::class, '--force' => true]);
    }

    private function catalogIsComplete(): bool
    {
        $plans = Plan::query()->active()->with('prices')->get();

        if ($plans->isEmpty()) {
            return false;
        }

        return $plans->every(fn (Plan $plan): bool => filled($plan->stripe_product_id)
            && filled($plan->priceFor(Interval::Monthly)?->stripe_price_id)
            && filled($plan->priceFor(Interval::Yearly)?->stripe_price_id));
    }
}
