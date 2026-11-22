<?php

declare(strict_types=1);

use App\Enums\Billing\Interval;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Support\Billing\OfferPricing;
use Illuminate\Support\Str;

/**
 * A Plan with one or both prices loaded in memory, so the math is tested
 * without the database.
 */
function pricedPlan(int $monthly, ?int $yearly = null): Plan
{
    $plan = Plan::factory()->make(['id' => (string) Str::uuid()]);
    $prices = collect([
        PlanPrice::factory()->make([
            'id' => (string) Str::uuid(),
            'plan_id' => $plan->id,
            'interval' => Interval::Monthly,
            'amount' => $monthly,
            'is_active' => true,
            'created_at' => now(),
        ]),
    ]);

    if ($yearly !== null) {
        $prices->push(PlanPrice::factory()->make([
            'id' => (string) Str::uuid(),
            'plan_id' => $plan->id,
            'interval' => Interval::Yearly,
            'amount' => $yearly,
            'is_active' => true,
            'created_at' => now(),
        ]));
    }

    $plan->setRelation('prices', $prices);

    return $plan;
}

test('a percentage discount rounds half up to the cent, as Stripe does', function (int $amount, float $percent, int $discount) {
    $offer = Offer::factory()->percent($percent)->make();

    expect(OfferPricing::discountFor($amount, $offer))->toBe($discount);
})->with([
    'the documented 10% of $19.99' => [1999, 10.0, 200],
    '20% of $11.90' => [1190, 20.0, 238],
    '25.5% of $11.90 rounds 303.45 down' => [1190, 25.5, 303],
    '25.5% of $11.99 rounds 305.745 up' => [1199, 25.5, 306],
    'a half cent rounds up' => [5, 50.0, 3],
]);

test('an amount discount is taken once, and never more than the price', function () {
    $five = Offer::factory()->amount(500)->make();
    $fifty = Offer::factory()->amount(5000)->make();

    expect(OfferPricing::discountFor(1190, $five))->toBe(500)
        ->and(OfferPricing::discountedAmount(1190, $five))->toBe(690)
        ->and(OfferPricing::discountFor(1190, $fifty))->toBe(1190)
        ->and(OfferPricing::discountedAmount(1190, $fifty))->toBe(0);
});

test('an annual price loses the amount once, not twelve times', function () {
    $offer = Offer::factory()->amount(500)->make();

    expect(OfferPricing::discountFor(11900, $offer))->toBe(500)
        ->and(OfferPricing::discountedAmount(11900, $offer))->toBe(11400);
});

test('a percentage on an annual price applies to the annual subtotal', function () {
    $offer = Offer::factory()->percent(20)->make();

    expect(OfferPricing::discountFor(11900, $offer))->toBe(2380)
        ->and(OfferPricing::discountedAmount(11900, $offer))->toBe(9520);
});

test('a repeating or forever coupon prices the first charge exactly like a once coupon', function () {
    $once = Offer::factory()->percent(20)->once()->make();
    $repeating = Offer::factory()->percent(20)->repeating(3)->make();
    $forever = Offer::factory()->percent(20)->forever()->make();

    expect(OfferPricing::discountedAmount(11900, $once))->toBe(9520)
        ->and(OfferPricing::discountedAmount(11900, $repeating))->toBe(9520)
        ->and(OfferPricing::discountedAmount(11900, $forever))->toBe(9520);
});

test('a trial-only offer has no discount', function () {
    $offer = Offer::factory()->trial(14)->make();

    expect(OfferPricing::discountFor(1190, $offer))->toBe(0)
        ->and(OfferPricing::discountedAmount(1190, $offer))->toBe(1190);
});

test('forPlan prices only a covered plan and interval', function () {
    $plan = pricedPlan(1190, 11900);
    $covered = Offer::factory()->percent(20)->covering([$plan], ['monthly'])->make();
    $yearly = Offer::factory()->percent(20)->covering([$plan], ['yearly'])->make();

    expect(OfferPricing::forPlan($covered, $plan, Interval::Monthly))->toBe([
        'regular' => 1190,
        'discount' => 238,
        'discounted' => 952,
    ])
        ->and(OfferPricing::forPlan($covered, $plan, Interval::Yearly))->toBeNull()
        ->and(OfferPricing::forPlan($yearly, $plan, Interval::Yearly))->toBe([
            'regular' => 11900,
            'discount' => 2380,
            'discounted' => 9520,
        ]);
});

test('forPlans prices every covered plan and interval in one call', function () {
    $mini = pricedPlan(1190, 11900);
    $plus = pricedPlan(2990, 29900);
    $offer = Offer::factory()->percent(50)->covering([$mini, $plus], ['monthly'])->make();

    $prices = OfferPricing::forPlans($offer, [$mini, $plus]);

    expect($prices)->toHaveCount(2)
        ->and($prices[$mini->id]['monthly']['discounted'])->toBe(595)
        ->and($prices[$plus->id]['monthly']['discounted'])->toBe(1495)
        ->and($prices[$mini->id]['yearly'])->toBeNull();
});
