<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Enums\Billing\Interval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'quota_bytes' => $this->quota_bytes,
            'ai_credits_per_month' => $this->ai_credits_per_month,
            'sort' => $this->sort,
            'is_archived' => $this->is_archived,
            'stripe_product_id' => $this->stripe_product_id,
            'prices' => [
                'monthly' => $this->price(Interval::Monthly),
                'yearly' => $this->price(Interval::Yearly),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The interval's active price, or null when the Plan sells no such price
     * today. A deactivated row is kept in the database but never listed here.
     *
     * @return array{id: string, amount: int, stripe_price_id: ?string, is_active: bool}|null
     */
    private function price(Interval $interval): ?array
    {
        $price = $this->priceFor($interval);

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
}
