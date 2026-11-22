<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\Billing\Interval;
use App\Exceptions\Catalog\StripeRejected;
use App\Models\Account;
use App\Models\CatalogSetting;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Services\Storage\AccountStorage;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeObject;

/**
 * The Catalog: Plans, their prices and the Catalog settings. The database is
 * the source of truth and this class is the only writer, so a change through
 * the internal API and one through `rxdesk:catalog-sync` go the same way:
 * Stripe first, then one database transaction, then the audit row. A Stripe
 * failure throws `StripeRejected` before anything is saved.
 *
 * Every Stripe Price a Plan ever had stays in `plan_prices`: a price change
 * deactivates its row and adds a new one, so a subscription on the old Price
 * still resolves to its Plan.
 */
class Catalog
{
    private const string PLAN_SLUG_METADATA = 'rxdesk_plan_slug';

    private const string CURRENCY = 'usd';

    /**
     * @var list<string>
     */
    private const array PLAN_FIELDS = ['name', 'quota_bytes', 'ai_credits_per_month', 'sort'];

    public function __construct(private readonly AccountStorage $storage) {}

    /**
     * @param  array{slug: string, name: string, quota_bytes: int, ai_credits_per_month: int, monthly_amount: int, sort: int}  $attributes
     */
    public function create(array $attributes, string $actor): Plan
    {
        $slug = (string) $attributes['slug'];
        $name = (string) $attributes['name'];
        $monthly = (int) $attributes['monthly_amount'];

        $this->ensureCreditsAtLeastTrial((int) $attributes['ai_credits_per_month']);

        $stripe = $this->attempt(function () use ($slug, $name, $monthly): array {
            $product = Cashier::stripe()->products->create([
                'name' => $name,
                'metadata' => [self::PLAN_SLUG_METADATA => $slug],
            ]);

            $monthlyPrice = $this->createStripePrice($slug, (string) $product->id, Interval::Monthly, $monthly);
            $yearlyPrice = $this->createStripePrice($slug, (string) $product->id, Interval::Yearly, $monthly * 10);

            return [
                'product_id' => (string) $product->id,
                'monthly_price_id' => (string) $monthlyPrice->id,
                'yearly_price_id' => (string) $yearlyPrice->id,
            ];
        });

        $plan = DB::transaction(function () use ($attributes, $slug, $name, $monthly, $stripe): Plan {
            $plan = Plan::create([
                'slug' => $slug,
                'name' => $name,
                'quota_bytes' => (int) $attributes['quota_bytes'],
                'ai_credits_per_month' => (int) $attributes['ai_credits_per_month'],
                'workspace_limit' => null,
                'sort' => (int) $attributes['sort'],
                'is_archived' => false,
                'stripe_product_id' => $stripe['product_id'],
            ]);

            $this->insertPrice($plan, Interval::Monthly, $monthly, $stripe['monthly_price_id']);
            $this->insertPrice($plan, Interval::Yearly, $monthly * 10, $stripe['yearly_price_id']);

            return $plan;
        });

        RecordAuditEntry::execute($actor, 'plan.create', $plan, [], $this->snapshot($plan->refresh()));

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Plan $plan, array $attributes, string $actor): Plan
    {
        if ($plan->is_archived) {
            throw ValidationException::withMessages([
                'plan' => 'An archived Plan cannot be changed.',
            ]);
        }

        $this->ensureCreditsAtLeastTrial($this->effectiveCredits($plan, $attributes));

        $before = $this->snapshot($plan);
        $name = $this->effectiveName($plan, $attributes);
        $nameChanged = $name !== $plan->name;
        $monthlyAmount = $this->requestedMonthlyAmount($attributes);
        $priceChanged = $monthlyAmount !== null && $monthlyAmount !== $plan->priceFor(Interval::Monthly)?->amount;

        $mirror = null;

        if ($nameChanged || $priceChanged) {
            $mirror = $this->mirrorPlan($plan, $name, $nameChanged, $monthlyAmount, $priceChanged);
        }

        $plan = DB::transaction(function () use ($plan, $attributes, $mirror, $monthlyAmount, $name): Plan {
            $fields = Arr::only($attributes, self::PLAN_FIELDS);
            $fields['name'] = $name;

            if ($mirror !== null) {
                $fields['stripe_product_id'] = $mirror['product_id'];
            }

            $plan->update($fields);

            if ($mirror !== null && $mirror['prices'] !== null && $monthlyAmount !== null) {
                $plan->prices()->where('is_active', true)->update(['is_active' => false]);
                $this->insertPrice($plan, Interval::Monthly, $monthlyAmount, $mirror['prices']['monthly']);
                $this->insertPrice($plan, Interval::Yearly, $monthlyAmount * 10, $mirror['prices']['yearly']);
            }

            return $plan;
        });

        RecordAuditEntry::execute($actor, 'plan.update', $plan, $before, $this->snapshot($plan->refresh()));

        return $plan;
    }

    public function archive(Plan $plan, string $actor): Plan
    {
        if ($plan->is_archived) {
            return $plan;
        }

        $before = $this->snapshot($plan);

        $this->attempt(function () use ($plan): void {
            foreach (Interval::cases() as $interval) {
                $price = $plan->priceFor($interval);

                if ($price !== null && filled($price->stripe_price_id)) {
                    Cashier::stripe()->prices->update($price->stripe_price_id, ['active' => false]);
                }
            }

            if (filled($plan->stripe_product_id)) {
                Cashier::stripe()->products->update($plan->stripe_product_id, ['active' => false]);
            }
        });

        DB::transaction(function () use ($plan): void {
            $plan->update(['is_archived' => true]);
            $plan->prices()->where('is_active', true)->update(['is_active' => false]);
        });

        RecordAuditEntry::execute($actor, 'plan.archive', $plan, $before, $this->snapshot($plan->refresh()));

        return $plan;
    }

    /**
     * Converges every active Plan to the current Stripe key. Returns how many
     * Plans it walked; a second run with the same key creates nothing.
     */
    public function sync(string $actor = 'cli'): int
    {
        $plans = Plan::query()
            ->active()
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        foreach ($plans as $plan) {
            $this->syncPlan($plan, $actor);
        }

        return $plans->count();
    }

    /**
     * The Owners whose Storage used would exceed the proposed Quota, largest
     * first. Read-only: the System admin decides before saving.
     *
     * @return list<array{account_id: string, owner_name: ?string, owner_email: ?string, used_bytes: int}>
     */
    public function quotaCutOwners(Plan $plan, int $quotaBytes): array
    {
        $accounts = Account::query()
            ->where('plan_id', $plan->id)
            ->whereNull('quota_bytes')
            ->whereNotNull('owner_id')
            ->with('owner')
            ->get();

        return $accounts
            ->map(fn (Account $account): array => [
                'account_id' => (string) $account->id,
                'owner_name' => $account->owner?->name,
                'owner_email' => $account->owner?->email,
                'used_bytes' => $this->storage->used($account),
            ])
            ->filter(fn (array $owner): bool => $owner['used_bytes'] > $quotaBytes)
            ->sortByDesc('used_bytes')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $attributes
     */
    public function updateSettings(array $attributes, string $actor): CatalogSetting
    {
        $current = CatalogSetting::current();
        $before = $this->settingsSnapshot($current);

        $values = [
            'trial_days' => data_get($attributes, 'trial_days', $current->trial_days),
            'tokens_per_credit' => data_get($attributes, 'tokens_per_credit', $current->tokens_per_credit),
            'trial_ai_credits' => data_get($attributes, 'trial_ai_credits', $current->trial_ai_credits),
        ];

        $this->ensureTrialCreditsFitPlans((int) $values['trial_ai_credits']);

        $setting = CatalogSetting::updateOrCreate(
            ['id' => CatalogSetting::SINGLETON_ID],
            $values,
        );

        RecordAuditEntry::execute($actor, 'catalog-settings.update', $setting, $before, $this->settingsSnapshot($setting));

        return $setting;
    }

    /**
     * Updates the Product and, when the price changed, creates the two new
     * Prices and deactivates the old ones. Returns the Product id and the new
     * Price ids.
     *
     * @return array{product_id: string, prices: array{monthly: string, yearly: string}|null}
     */
    private function mirrorPlan(Plan $plan, string $name, bool $nameChanged, ?int $monthlyAmount, bool $priceChanged): array
    {
        return $this->attempt(function () use ($plan, $name, $nameChanged, $monthlyAmount, $priceChanged): array {
            $productId = (string) $plan->stripe_product_id;

            if (blank($productId)) {
                $product = Cashier::stripe()->products->create([
                    'name' => $name,
                    'metadata' => [self::PLAN_SLUG_METADATA => $plan->slug],
                ]);
                $productId = (string) $product->id;
            } elseif ($nameChanged) {
                Cashier::stripe()->products->update($productId, ['name' => $name]);
            }

            if (! $priceChanged || $monthlyAmount === null) {
                return ['product_id' => $productId, 'prices' => null];
            }

            $monthly = $this->createStripePrice($plan->slug, $productId, Interval::Monthly, $monthlyAmount);
            $yearly = $this->createStripePrice($plan->slug, $productId, Interval::Yearly, $monthlyAmount * 10);

            foreach (Interval::cases() as $interval) {
                $price = $plan->priceFor($interval);

                if ($price !== null && filled($price->stripe_price_id)) {
                    Cashier::stripe()->prices->update($price->stripe_price_id, ['active' => false]);
                }
            }

            return [
                'product_id' => $productId,
                'prices' => ['monthly' => (string) $monthly->id, 'yearly' => (string) $yearly->id],
            ];
        });
    }

    private function syncPlan(Plan $plan, string $actor): void
    {
        $before = $this->snapshot($plan);

        $product = $this->attempt(fn (): array => $this->syncProduct($plan));
        $productId = $product['id'];

        $prices = [];

        foreach (Interval::cases() as $interval) {
            $outcome = $this->attempt(fn (): ?array => $this->syncInterval($plan, $productId, $interval));

            if ($outcome !== null) {
                $prices[] = $outcome;
            }
        }

        $pricesChanged = collect($prices)->contains(fn (array $outcome): bool => $outcome['action'] !== 'match');
        $productChanged = $product['changed'];

        if (! $pricesChanged && ! $productChanged) {
            return;
        }

        DB::transaction(function () use ($plan, $productId, $prices): void {
            if ($plan->stripe_product_id !== $productId) {
                $plan->update(['stripe_product_id' => $productId]);
            }

            foreach ($prices as $outcome) {
                if ($outcome['action'] === 'match') {
                    continue;
                }

                if ($outcome['action'] === 'fill') {
                    $outcome['row']->update(['stripe_price_id' => $outcome['stripe_price_id']]);

                    continue;
                }

                $plan->prices()
                    ->where('interval', $outcome['interval'])
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                $this->insertPrice($plan, $outcome['interval'], $outcome['amount'], $outcome['stripe_price_id']);
            }
        });

        RecordAuditEntry::execute($actor, 'plan.sync', $plan, $before, $this->snapshot($plan->refresh()));
    }

    /**
     * @return array{id: string, changed: bool}
     */
    private function syncProduct(Plan $plan): array
    {
        $id = (string) $plan->stripe_product_id;

        if (blank($id)) {
            return ['id' => $this->createStripeProduct($plan), 'changed' => true];
        }

        $product = $this->retrieveProduct($id);

        if ($product === null || (string) data_get($product, 'metadata.'.self::PLAN_SLUG_METADATA) !== $plan->slug) {
            return ['id' => $this->createStripeProduct($plan), 'changed' => true];
        }

        if ((string) $product->name !== $plan->name) {
            Cashier::stripe()->products->update($id, ['name' => $plan->name]);

            return ['id' => $id, 'changed' => true];
        }

        return ['id' => $id, 'changed' => false];
    }

    private function createStripeProduct(Plan $plan): string
    {
        $product = Cashier::stripe()->products->create([
            'name' => $plan->name,
            'metadata' => [self::PLAN_SLUG_METADATA => $plan->slug],
        ]);

        return (string) $product->id;
    }

    /**
     * @return array{interval: Interval, action: string, amount: int, stripe_price_id: string, row: PlanPrice}|null
     */
    private function syncInterval(Plan $plan, string $productId, Interval $interval): ?array
    {
        $amount = $this->currentAmount($plan, $interval);

        if ($amount === null) {
            return null;
        }

        $row = $plan->priceFor($interval);

        if ($row !== null && filled($row->stripe_price_id)) {
            $price = $this->retrievePrice($row->stripe_price_id);

            if ($price !== null && $this->priceMatches($price, $productId, $amount, $interval)) {
                return $this->outcome($interval, 'match', $amount, (string) $row->stripe_price_id, $row);
            }

            if ($price !== null) {
                Cashier::stripe()->prices->update((string) $row->stripe_price_id, ['active' => false]);
            }

            $created = $this->createStripePrice($plan->slug, $productId, $interval, $amount);

            return $this->outcome($interval, 'replace', $amount, (string) $created->id, $row);
        }

        $created = $this->createStripePrice($plan->slug, $productId, $interval, $amount);

        if ($row !== null && $row->amount === $amount) {
            return $this->outcome($interval, 'fill', $amount, (string) $created->id, $row);
        }

        return $this->outcome($interval, 'replace', $amount, (string) $created->id, $row);
    }

    /**
     * @return array{interval: Interval, action: string, amount: int, stripe_price_id: string, row: PlanPrice|null}
     */
    private function outcome(Interval $interval, string $action, int $amount, string $stripePriceId, ?PlanPrice $row): array
    {
        return [
            'interval' => $interval,
            'action' => $action,
            'amount' => $amount,
            'stripe_price_id' => $stripePriceId,
            'row' => $row,
        ];
    }

    /**
     * The amount an interval sells at today: the active monthly row, and ten
     * times it for the year. Null when the Plan has no active monthly row.
     */
    private function currentAmount(Plan $plan, Interval $interval): ?int
    {
        $monthly = $plan->priceFor(Interval::Monthly);

        if ($monthly === null) {
            return null;
        }

        if ($interval === Interval::Monthly) {
            return (int) $monthly->amount;
        }

        return (int) $monthly->amount * 10;
    }

    private function priceMatches(StripeObject $price, string $productId, int $amount, Interval $interval): bool
    {
        return (string) $price->product === $productId
            && (int) $price->unit_amount === $amount
            && (string) data_get($price, 'recurring.interval') === $interval->stripeInterval()
            && (bool) $price->active;
    }

    private function retrieveProduct(string $id): ?StripeObject
    {
        try {
            return Cashier::stripe()->products->retrieve($id);
        } catch (InvalidRequestException $exception) {
            if ($exception->getStripeCode() === 'resource_missing') {
                return null;
            }

            throw $exception;
        }
    }

    private function retrievePrice(string $id): ?StripeObject
    {
        try {
            return Cashier::stripe()->prices->retrieve($id);
        } catch (InvalidRequestException $exception) {
            if ($exception->getStripeCode() === 'resource_missing') {
                return null;
            }

            throw $exception;
        }
    }

    private function createStripePrice(string $slug, string $productId, Interval $interval, int $amount): StripeObject
    {
        return Cashier::stripe()->prices->create([
            'product' => $productId,
            'currency' => self::CURRENCY,
            'unit_amount' => $amount,
            'recurring' => ['interval' => $interval->stripeInterval()],
            'metadata' => [
                self::PLAN_SLUG_METADATA => $slug,
                'rxdesk_interval' => $interval->value,
            ],
        ]);
    }

    private function insertPrice(Plan $plan, Interval $interval, int $amount, string $stripePriceId): PlanPrice
    {
        return $plan->prices()->create([
            'interval' => $interval,
            'amount' => $amount,
            'stripe_price_id' => $stripePriceId,
            'is_active' => true,
        ]);
    }

    private function effectiveName(Plan $plan, array $attributes): string
    {
        if (array_key_exists('name', $attributes)) {
            return (string) $attributes['name'];
        }

        return (string) $plan->name;
    }

    private function effectiveCredits(Plan $plan, array $attributes): int
    {
        if (array_key_exists('ai_credits_per_month', $attributes)) {
            return (int) $attributes['ai_credits_per_month'];
        }

        return (int) $plan->ai_credits_per_month;
    }

    /**
     * The monthly amount the PATCH asks for, or null when it leaves the price
     * alone. A Plan with no active monthly row always needs one when a price
     * is given.
     */
    private function requestedMonthlyAmount(array $attributes): ?int
    {
        if (! array_key_exists('monthly_amount', $attributes)) {
            return null;
        }

        return (int) $attributes['monthly_amount'];
    }

    private function ensureCreditsAtLeastTrial(int $credits): void
    {
        $trial = (int) CatalogSetting::current()->trial_ai_credits;

        if ($credits >= $trial) {
            return;
        }

        throw ValidationException::withMessages([
            'ai_credits_per_month' => "The Plan's monthly AI credits cannot be below the Trial's {$trial}.",
        ]);
    }

    private function ensureTrialCreditsFitPlans(int $trialCredits): void
    {
        $smallest = Plan::query()
            ->active()
            ->whereNotNull('ai_credits_per_month')
            ->min('ai_credits_per_month');

        if (blank($smallest) || $trialCredits <= (int) $smallest) {
            return;
        }

        throw ValidationException::withMessages([
            'trial_ai_credits' => "The Trial's AI credits cannot be above the smallest active Plan's monthly AI credits ({$smallest}).",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Plan $plan): array
    {
        return [
            'name' => $plan->name,
            'quota_bytes' => $plan->quota_bytes,
            'ai_credits_per_month' => $plan->ai_credits_per_month,
            'sort' => $plan->sort,
            'is_archived' => $plan->is_archived,
            'stripe_product_id' => $plan->stripe_product_id,
            'prices' => [
                'monthly' => $this->priceSnapshot($plan, Interval::Monthly),
                'yearly' => $this->priceSnapshot($plan, Interval::Yearly),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function priceSnapshot(Plan $plan, Interval $interval): ?array
    {
        $price = $plan->priceFor($interval);

        if ($price === null) {
            return null;
        }

        return [
            'id' => $price->id,
            'amount' => $price->amount,
            'stripe_price_id' => $price->stripe_price_id,
            'is_active' => $price->is_active,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function settingsSnapshot(CatalogSetting $setting): array
    {
        return [
            'trial_days' => $setting->trial_days,
            'tokens_per_credit' => $setting->tokens_per_credit,
            'trial_ai_credits' => $setting->trial_ai_credits,
        ];
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $work
     * @return TValue
     */
    private function attempt(Closure $work): mixed
    {
        try {
            return $work();
        } catch (ApiErrorException $exception) {
            Log::error('Stripe rejected a Catalog change.', [
                'status' => $exception->getHttpStatus(),
                'code' => $exception->getStripeCode(),
                'message' => $exception->getMessage(),
            ]);

            throw StripeRejected::because($exception);
        }
    }
}
