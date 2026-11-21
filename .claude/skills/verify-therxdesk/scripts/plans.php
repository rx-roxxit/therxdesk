<?php

declare(strict_types=1);

/*
 * The app's Plan catalog on the DEV database: the active Plans' active
 * plan_prices rows, and the sandbox Stripe price ids selling.sh writes back.
 * Never run it against production. selling.sh runs it in the app image.
 *
 *   php plans.php catalog   print the rows as one JSON array
 *   php plans.php attach    read that array with `price_id` set on stdin, write
 *                           each id into its plan and interval's active row
 *
 * Catalog row: {"slug":"mini","interval":"monthly","amount":1190,"stripe_price_id":null}
 * Attach row:  {"slug":"mini","interval":"monthly","price_id":"price_..."}
 *
 * A row that already holds a different id is archived and a new active row
 * takes its place: a plan_prices row is never deleted.
 */

use App\Enums\Billing\Interval;
use App\Models\Plan;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

$root = '/var/www/html';
require "{$root}/vendor/autoload.php";
$app = require "{$root}/bootstrap/app.php";
$app->make(Kernel::class)->bootstrap();

/**
 * The active Plans' active price rows, sorted by the Plan's sort and monthly
 * before yearly. A Plan with no active row for an interval is left out.
 *
 * @return array<int, array{slug: string, interval: string, amount: int, stripe_price_id: ?string}>
 */
function catalog(): array
{
    $rows = [];

    foreach (Plan::query()->active()->with('prices')->orderBy('sort')->get() as $plan) {
        foreach (Interval::cases() as $interval) {
            $price = $plan->priceFor($interval);

            if ($price === null) {
                continue;
            }

            $rows[] = [
                'slug' => $plan->slug,
                'interval' => $interval->value,
                'amount' => $price->amount,
                'stripe_price_id' => $price->stripe_price_id,
            ];
        }
    }

    return $rows;
}

/**
 * Write each sandbox price id into its plan and interval's active row: the
 * same id is a no-op, an empty id is filled in place, and a different id
 * creates a new active row and archives the old one.
 *
 * @throws RuntimeException
 */
function attach(string $json): void
{
    $rows = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($rows)) {
        throw new RuntimeException('attach reads the catalog JSON array on stdin');
    }

    foreach ($rows as $row) {
        $slug = (string) data_get($row, 'slug', '');
        $interval = Interval::tryFrom((string) data_get($row, 'interval', ''));
        $priceId = (string) data_get($row, 'price_id', '');

        if ($slug === '' || $interval === null || ! Str::startsWith($priceId, 'price_')) {
            throw new RuntimeException('bad row '.json_encode($row));
        }

        $plan = Plan::query()->where('slug', $slug)->first();

        if ($plan === null) {
            throw new RuntimeException("no Plan {$slug}");
        }

        $price = $plan->priceFor($interval);

        if ($price === null) {
            throw new RuntimeException("{$slug} {$interval->value} has no active plan_prices row");
        }

        if ($price->stripe_price_id === $priceId) {
            echo "{$slug} {$interval->value}: unchanged ({$priceId})\n";

            continue;
        }

        if (blank($price->stripe_price_id)) {
            $price->update(['stripe_price_id' => $priceId]);
            echo "{$slug} {$interval->value}: {$priceId}\n";

            continue;
        }

        $previous = $price->stripe_price_id;
        $price->update(['is_active' => false]);

        $plan->prices()->create([
            'interval' => $interval,
            'amount' => $price->amount,
            'stripe_price_id' => $priceId,
            'is_active' => true,
        ]);

        echo "{$slug} {$interval->value}: {$priceId} (new row; {$previous} archived)\n";
    }
}

$command = $argv[1] ?? '';

if ($command === 'catalog') {
    echo json_encode(catalog(), JSON_THROW_ON_ERROR), PHP_EOL;
    exit(0);
}

if ($command === 'attach') {
    try {
        attach(stream_get_contents(STDIN) ?: '');
    } catch (Throwable $exception) {
        fwrite(STDERR, "[plans] FAILED: {$exception->getMessage()}\n");
        exit(1);
    }

    exit(0);
}

fwrite(STDERR, "usage: plans.php catalog|attach\n");
exit(2);
