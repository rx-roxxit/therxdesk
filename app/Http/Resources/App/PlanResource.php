<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use App\Enums\Billing\Interval;
use App\Models\Plan;
use App\Support\Billing\OfferCopy;
use App\Support\ByteSize;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
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
            'workspace_limit' => $this->workspace_limit,
            'quota_bytes' => $this->quota_bytes,
            'ai_credits_per_month' => $this->ai_credits_per_month,
            'quota_label' => filled($this->quota_bytes) ? ByteSize::format((int) $this->quota_bytes) : null,
            'ai_credits_label' => filled($this->ai_credits_per_month) ? Number::format((int) $this->ai_credits_per_month) : null,
            'sort' => $this->sort,
            'monthly_amount' => $this->priceFor(Interval::Monthly)?->amount,
            'yearly_amount' => $this->priceFor(Interval::Yearly)?->amount,
            'monthly_price' => $this->price(Interval::Monthly),
            'yearly_price' => $this->price(Interval::Yearly),
            'yearly_monthly_price' => $this->yearlyMonthlyPrice(),
        ];
    }

    /**
     * The price of an interval in the reader's locale, so no screen formats
     * money itself.
     */
    private function price(Interval $interval): ?string
    {
        $amount = $this->priceFor($interval)?->amount;

        if ($amount === null) {
            return null;
        }

        return OfferCopy::money((int) $amount);
    }

    /**
     * The per-month figure a yearly price is shown at. Computed here, never by
     * a Vue computed.
     */
    private function yearlyMonthlyPrice(): ?string
    {
        $amount = $this->priceFor(Interval::Yearly)?->amount;

        if ($amount === null) {
            return null;
        }

        return OfferCopy::money((int) round($amount / 12));
    }
}
