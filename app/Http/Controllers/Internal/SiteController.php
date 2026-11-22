<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Resources\Internal\SitePlanResource;
use App\Models\Offer;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController
{
    public function __invoke(Request $request): JsonResponse
    {
        $plans = Plan::query()
            ->active()
            ->orderBy('sort')
            ->orderBy('name')
            ->with('prices')
            ->get();

        $offer = Offer::query()
            ->available()
            ->general()
            ->latestStarted()
            ->first();

        return response()->json([
            'offer' => $this->offer($offer),
            'plans' => $plans
                ->map(fn (Plan $plan): array => SitePlanResource::make($plan, $offer)->resolve($request))
                ->all(),
        ]);
    }

    /**
     * The running general Offer, as data the website can price and format in
     * the visitor's locale. A link Offer is never published here.
     *
     * @return array<string, mixed>|null
     */
    private function offer(?Offer $offer): ?array
    {
        if ($offer === null) {
            return null;
        }

        return [
            'slug' => $offer->slug,
            'name' => $offer->name,
            'trial_days' => $offer->trial_days,
            'starts_at' => $offer->starts_at,
            'ends_at' => $offer->ends_at,
            'discount' => $offer->hasCoupon() ? [
                'type' => $offer->discount_type->value,
                'percent_off' => $offer->percent_off,
                'amount_off' => $offer->amount_off,
                'currency' => 'usd',
                'duration' => $offer->duration?->value,
                'duration_in_months' => $offer->duration_in_months,
            ] : null,
            'plan_ids' => $offer->plan_ids,
            'intervals' => $offer->coveredIntervals(),
        ];
    }
}
