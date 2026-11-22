<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use App\Models\Plan;
use App\Support\ByteSize;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * A Plan on the Welcome plan step: PlanResource plus the two labels the cards
 * show, so the frontend never formats a size or a count itself.
 *
 * @mixin Plan
 */
class WelcomePlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...PlanResource::make($this->resource)->resolve($request),
            'quota_label' => filled($this->quota_bytes) ? ByteSize::format((int) $this->quota_bytes) : null,
            'ai_credits_label' => filled($this->ai_credits_per_month) ? Number::format((int) $this->ai_credits_per_month) : null,
        ];
    }
}
