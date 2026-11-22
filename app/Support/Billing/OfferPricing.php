<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Enums\Billing\Interval;
use App\Enums\Billing\OfferDiscountType;
use App\Models\Offer;
use App\Models\Plan;

/**
 * The only place that turns an Offer into money. It mirrors Stripe's own
 * arithmetic: a percentage discount is computed on the invoice subtotal and
 * rounded half up to the cent, an amount discount is taken once (so an annual
 * price loses the same amount as a monthly one), and neither can take more
 * than the subtotal.
 *
 * A repeating coupon applies to the first invoice, and on a yearly price the
 * next invoice falls outside its duration, so its first charge is discounted
 * once too. This class prices the first charge, which is what every screen
 * shows.
 */
final class OfferPricing
{
    /**
     * The discount in cents, never more than the amount it applies to.
     */
    public static function discountFor(int $amount, Offer $offer): int
    {
        if (! $offer->hasCoupon()) {
            return 0;
        }

        if ($offer->discount_type === OfferDiscountType::Percent) {
            $discount = (int) round($amount * $offer->percent_off / 100);
        } else {
            $discount = (int) $offer->amount_off;
        }

        return max(0, min($amount, $discount));
    }

    public static function discountedAmount(int $amount, Offer $offer): int
    {
        return $amount - self::discountFor($amount, $offer);
    }

    /**
     * The regular, discount and discounted amount of one Plan × interval, or
     * null when the Offer does not cover it or the Plan sells no such price.
     *
     * @return array{regular: int, discount: int, discounted: int}|null
     */
    public static function forPlan(Offer $offer, Plan $plan, Interval $interval): ?array
    {
        if (! $offer->covers($plan, $interval)) {
            return null;
        }

        $regular = $plan->priceFor($interval)?->amount;

        if ($regular === null) {
            return null;
        }

        $regular = (int) $regular;
        $discount = self::discountFor($regular, $offer);

        return [
            'regular' => $regular,
            'discount' => $discount,
            'discounted' => $regular - $discount,
        ];
    }

    /**
     * Every covered Plan × interval, keyed by Plan id. One call prices the
     * whole Catalog the way the plan step, the change-plan cards and the
     * website show it.
     *
     * @param  iterable<Plan>  $plans
     * @return array<string, array{monthly: array{regular: int, discount: int, discounted: int}|null, yearly: array{regular: int, discount: int, discounted: int}|null}>
     */
    public static function forPlans(Offer $offer, iterable $plans): array
    {
        $prices = [];

        foreach ($plans as $plan) {
            $prices[(string) $plan->id] = [
                'monthly' => self::forPlan($offer, $plan, Interval::Monthly),
                'yearly' => self::forPlan($offer, $plan, Interval::Yearly),
            ];
        }

        return $prices;
    }
}
