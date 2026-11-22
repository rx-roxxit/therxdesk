<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Enums\Billing\Interval;
use App\Models\Offer;
use App\Models\Plan;
use App\Support\Billing\OfferPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Plan the website prices. The discounted amounts come from the same class
 * the app uses, rounded as Stripe rounds; the site formats them in the
 * visitor's locale.
 *
 * @mixin Plan
 */
class SitePlanResource extends JsonResource
{
    public function __construct(Plan $resource, private readonly ?Offer $offer = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'quota_bytes' => $this->quota_bytes,
            'ai_credits_per_month' => $this->ai_credits_per_month,
            'monthly' => $this->priceFor(Interval::Monthly)?->amount,
            'yearly' => $this->priceFor(Interval::Yearly)?->amount,
            'offer' => [
                'monthly' => $this->offerAmount(Interval::Monthly),
                'yearly' => $this->offerAmount(Interval::Yearly),
            ],
        ];
    }

    /**
     * @return array{regular: int, discount: int, discounted: int}|null
     */
    private function offerAmount(Interval $interval): ?array
    {
        if ($this->offer === null) {
            return null;
        }

        return OfferPricing::forPlan($this->offer, $this->resource, $interval);
    }
}
