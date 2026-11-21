<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use App\Enums\Billing\Interval;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'sort' => $this->sort,
            'monthly_amount' => $this->priceFor(Interval::Monthly)?->amount,
            'yearly_amount' => $this->priceFor(Interval::Yearly)?->amount,
        ];
    }
}
