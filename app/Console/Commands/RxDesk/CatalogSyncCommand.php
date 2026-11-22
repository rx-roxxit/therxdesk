<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Exceptions\Catalog\StripeRejected;
use App\Services\Catalog\Catalog;
use Illuminate\Console\Command;

/**
 * Converges every active Plan to the current Stripe key: Products and Prices
 * are created when missing and their ids written into `plans` and
 * `plan_prices`. Run it after a Stripe key change (test to live, or a new
 * Operator's account). Running it twice with the same key creates nothing.
 */
class CatalogSyncCommand extends Command
{
    protected $signature = 'rxdesk:catalog-sync';

    protected $description = 'Create or update the Stripe Products and Prices of every active Plan';

    public function handle(Catalog $catalog): int
    {
        try {
            $synced = $catalog->sync('cli');
        } catch (StripeRejected $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced {$synced} Plans.");

        return self::SUCCESS;
    }
}
