<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Enums\Billing\Interval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SitePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'quota_bytes' => $this->quota_bytes,
            'ai_credits_per_month' => $this->ai_credits_per_month,
            'monthly' => $this->priceFor(Interval::Monthly)?->amount,
            'yearly' => $this->priceFor(Interval::Yearly)?->amount,
        ];
    }
}
