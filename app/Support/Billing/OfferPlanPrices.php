<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Enums\Billing\Interval;
use App\Models\Offer;
use App\Models\Plan;

/**
 * The shape every screen shows for an Offer's covered prices: a badge, the
 * struck regular price, the discounted price and the Offer's line, written in
 * the reader's locale. The amounts come from OfferPricing; this class only
 * formats them.
 */
final class OfferPlanPrices
{
    /**
     * Every covered Plan × interval, keyed by Plan id.
     *
     * @param  iterable<Plan>  $plans
     * @return array<string, array{monthly: array<string, string>|null, yearly: array<string, string>|null}>
     */
    public static function for(Offer $offer, iterable $plans): array
    {
        $prices = [];

        foreach (OfferPricing::forPlans($offer, $plans) as $planId => $row) {
            $prices[$planId] = [
                Interval::Monthly->value => self::slot($offer, $row['monthly'], Interval::Monthly),
                Interval::Yearly->value => self::slot($offer, $row['yearly'], Interval::Yearly),
            ];
        }

        return $prices;
    }

    /**
     * @param  array{regular: int, discount: int, discounted: int}|null  $pricing
     * @return array{badge: string, regular: string, discounted: string, regular_per_month: string, discounted_per_month: string, line: string}|null
     */
    public static function slot(Offer $offer, ?array $pricing, Interval $interval): ?array
    {
        if ($pricing === null || $pricing['discount'] <= 0) {
            return null;
        }

        $perMonth = fn (int $amount): int => $interval === Interval::Yearly
            ? (int) round($amount / 12)
            : $amount;

        return [
            'badge' => (string) OfferCopy::discountLabel($offer),
            'regular' => OfferCopy::money($pricing['regular']),
            'discounted' => OfferCopy::money($pricing['discounted']),
            'regular_per_month' => OfferCopy::money($perMonth($pricing['regular'])),
            'discounted_per_month' => OfferCopy::money($perMonth($pricing['discounted'])),
            'line' => OfferCopy::line($offer, $interval),
        ];
    }
}
