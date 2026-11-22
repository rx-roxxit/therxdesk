<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Offer
 */
class OfferAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'reach' => $this->reach?->value,
            'discount' => $this->discount(),
            'stripe_coupon_id' => $this->stripe_coupon_id,
            'trial_days' => $this->trial_days,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'max_redemptions' => $this->max_redemptions,
            'show_banner' => $this->show_banner,
            'plan_ids' => $this->plan_ids,
            'intervals' => $this->coveredIntervals(),
            'is_ended' => $this->hasEnded(),
            'redemptions_count' => $this->whenCounted('redemptions'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{type: string, percent_off: float|null, amount_off: int|null, currency: string, duration: string|null, duration_in_months: int|null}|null
     */
    private function discount(): ?array
    {
        if (! $this->hasCoupon()) {
            return null;
        }

        return [
            'type' => $this->discount_type->value,
            'percent_off' => $this->percent_off,
            'amount_off' => $this->amount_off,
            'currency' => 'usd',
            'duration' => $this->duration?->value,
            'duration_in_months' => $this->duration_in_months,
        ];
    }
}
