<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Actions\Audit\RecordAuditEntry;
use App\Enums\Billing\Interval;
use App\Enums\Billing\OfferDuration;
use App\Exceptions\Catalog\StripeRejected;
use App\Models\Offer;
use App\Models\Plan;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;

/**
 * The admin-managed Offers (campaigns). The database is the source of truth
 * and this class is the only writer: Stripe first (the coupon), then one
 * database transaction, then the audit row. Discount, duration and coverage
 * are written once; the row is read-only once its end has come and is never
 * deleted.
 */
class OfferCatalog
{
    public const string CURRENCY = 'usd';

    /**
     * The fields an update may still change. Discount, duration, coverage and
     * reach are locked, and `slug` is the link's identity.
     *
     * @var list<string>
     */
    private const array EDITABLE_FIELDS = [
        'name',
        'starts_at',
        'ends_at',
        'max_redemptions',
        'show_banner',
        'trial_days',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, string $actor): Offer
    {
        $plans = $this->coveredPlans($attributes);
        $intervals = $this->coveredIntervals($attributes);

        if ($plans->isEmpty()) {
            throw ValidationException::withMessages(['plan_ids' => 'Cover at least one active Plan.']);
        }

        $this->ensureBenefit($attributes);
        $this->ensureCoveredPlansHaveProducts($plans, $attributes);

        $couponId = $this->attempt(fn (): ?string => $this->createCoupon($attributes, $plans));

        $offer = DB::transaction(fn (): Offer => Offer::create([
            'slug' => (string) $attributes['slug'],
            'name' => (string) $attributes['name'],
            'reach' => $attributes['reach'],
            'discount_type' => data_get($attributes, 'coupon.type'),
            'percent_off' => data_get($attributes, 'coupon.percent_off'),
            'amount_off' => data_get($attributes, 'coupon.amount_off'),
            'duration' => data_get($attributes, 'coupon.duration'),
            'duration_in_months' => data_get($attributes, 'coupon.duration_in_months'),
            'stripe_coupon_id' => $couponId,
            'trial_days' => data_get($attributes, 'trial_days'),
            'starts_at' => data_get($attributes, 'starts_at'),
            'ends_at' => data_get($attributes, 'ends_at'),
            'max_redemptions' => data_get($attributes, 'max_redemptions'),
            'show_banner' => (bool) data_get($attributes, 'show_banner', false),
            'plan_ids' => $plans->pluck('id')->map(fn ($id): string => (string) $id)->all(),
            'intervals' => $intervals,
        ]));

        RecordAuditEntry::execute($actor, 'offer.create', $offer, [], $this->snapshot($offer));

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Offer $offer, array $attributes, string $actor): Offer
    {
        $this->ensureEditable($offer);

        $before = $this->snapshot($offer);
        $fields = Arr::only($attributes, self::EDITABLE_FIELDS);
        $this->ensureDatesInOrder($offer, $fields);

        $offer->update($fields);

        RecordAuditEntry::execute($actor, 'offer.update', $offer, $before, $this->snapshot($offer->refresh()));

        return $offer;
    }

    public function end(Offer $offer, string $actor): Offer
    {
        if ($offer->hasEnded()) {
            return $offer;
        }

        $before = $this->snapshot($offer);
        $offer->update(['ends_at' => now()]);

        RecordAuditEntry::execute($actor, 'offer.end', $offer, $before, $this->snapshot($offer->refresh()));

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, Plan>
     */
    private function coveredPlans(array $attributes)
    {
        $ids = data_get($attributes, 'plan_ids');

        if (blank($ids)) {
            return Plan::query()->active()->orderBy('sort')->orderBy('name')->get();
        }

        $plans = Plan::query()->whereIn('id', $ids)->get();

        if ($plans->count() !== count($ids)) {
            throw ValidationException::withMessages(['plan_ids' => 'Every covered Plan must exist.']);
        }

        return $plans;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    private function coveredIntervals(array $attributes): array
    {
        $values = data_get($attributes, 'intervals');

        if (blank($values)) {
            return [Interval::Monthly->value];
        }

        return array_values(array_map(
            fn (Interval|string $interval): string => $interval instanceof Interval ? $interval->value : (string) $interval,
            $values,
        ));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ensureBenefit(array $attributes): void
    {
        if (filled(data_get($attributes, 'coupon'))) {
            return;
        }

        if ((int) data_get($attributes, 'trial_days', 0) > 0) {
            return;
        }

        throw ValidationException::withMessages([
            'coupon' => 'An Offer needs a coupon, trial days, or both.',
        ]);
    }

    /**
     * @param  Collection<int, Plan>  $plans
     * @param  array<string, mixed>  $attributes
     */
    private function ensureCoveredPlansHaveProducts($plans, array $attributes): void
    {
        if (blank(data_get($attributes, 'coupon'))) {
            return;
        }

        $missing = $plans->filter(fn (Plan $plan): bool => blank($plan->stripe_product_id));

        if ($missing->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'plan_ids' => 'Sync the Catalog to Stripe before covering these Plans: '.$missing->pluck('name')->implode(', '),
        ]);
    }

    /**
     * @param  Collection<int, Plan>  $plans
     * @param  array<string, mixed>  $attributes
     */
    private function createCoupon(array $attributes, $plans): ?string
    {
        $coupon = data_get($attributes, 'coupon');

        if (blank($coupon)) {
            return null;
        }

        $id = self::couponIdFor((string) $attributes['slug']);
        $stripe = Cashier::stripe();

        $existing = $this->findCoupon($id);

        if ($existing !== null) {
            return (string) $existing->id;
        }

        $params = [
            'id' => $id,
            'name' => Str::limit((string) $attributes['name'], 40, ''),
            'duration' => (string) data_get($coupon, 'duration'),
            'metadata' => ['rxdesk_offer_slug' => (string) $attributes['slug']],
            'applies_to' => ['products' => $plans->pluck('stripe_product_id')->filter()->values()->all()],
        ];

        if (data_get($coupon, 'type') === 'percent') {
            $params['percent_off'] = (float) data_get($coupon, 'percent_off');
        } else {
            $params['amount_off'] = (int) data_get($coupon, 'amount_off');
            $params['currency'] = self::CURRENCY;
        }

        if ((string) data_get($coupon, 'duration') === OfferDuration::Repeating->value) {
            $params['duration_in_months'] = (int) data_get($coupon, 'duration_in_months');
        }

        try {
            return (string) $stripe->coupons->create($params)->id;
        } catch (InvalidRequestException $exception) {
            // A concurrent create of the same slug already made it. The row
            // is unique on slug, so the loser fails after this call anyway.
            if ($exception->getStripeCode() === 'resource_already_exists') {
                return (string) $this->findCoupon($id)?->id;
            }

            throw $exception;
        }
    }

    /**
     * The coupon id is stable for a slug, so a retried create after a failed
     * Stripe call (or a repeated sandbox journey) reuses the coupon instead of
     * making a second one. The discount is locked, so its parameters never
     * change.
     */
    public static function couponIdFor(string $slug): string
    {
        return 'rxdesk-offer-'.substr(hash('sha256', $slug), 0, 24);
    }

    private function findCoupon(string $id): mixed
    {
        try {
            return Cashier::stripe()->coupons->retrieve($id);
        } catch (InvalidRequestException $exception) {
            if ($exception->getStripeCode() === 'resource_missing') {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function attempt(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ApiErrorException $exception) {
            throw StripeRejected::because($exception);
        }
    }

    private function ensureEditable(Offer $offer): void
    {
        if ($offer->hasEnded()) {
            throw ValidationException::withMessages([
                'offer' => 'An ended Offer cannot be changed. Create a new Offer.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function ensureDatesInOrder(Offer $offer, array $fields): void
    {
        $startsAt = data_get($fields, 'starts_at', $offer->starts_at);
        $endsAt = data_get($fields, 'ends_at', $offer->ends_at);

        if (blank($startsAt) || blank($endsAt)) {
            return;
        }

        if (Carbon::parse($endsAt)->lte(Carbon::parse($startsAt))) {
            throw ValidationException::withMessages([
                'ends_at' => 'The end must come after the start.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Offer $offer): array
    {
        return [
            'slug' => $offer->slug,
            'name' => $offer->name,
            'reach' => $offer->reach?->value,
            'discount_type' => $offer->discount_type?->value,
            'percent_off' => $offer->percent_off,
            'amount_off' => $offer->amount_off,
            'duration' => $offer->duration?->value,
            'duration_in_months' => $offer->duration_in_months,
            'stripe_coupon_id' => $offer->stripe_coupon_id,
            'trial_days' => $offer->trial_days,
            'starts_at' => $offer->starts_at?->toIso8601String(),
            'ends_at' => $offer->ends_at?->toIso8601String(),
            'max_redemptions' => $offer->max_redemptions,
            'show_banner' => $offer->show_banner,
            'plan_ids' => $offer->plan_ids,
            'intervals' => $offer->intervals,
        ];
    }
}
