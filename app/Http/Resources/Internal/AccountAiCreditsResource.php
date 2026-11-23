<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Account;
use App\Services\Ai\AiCredits;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountAiCreditsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $credits = app(AiCredits::class);

        return [
            'id' => $this->id,
            'ai_credits_per_month' => $this->ai_credits_per_month,
            'monthly_credits' => $credits->allowance($this->resource),
            'used' => $credits->used($this->resource),
            'remaining' => $credits->remaining($this->resource),
            'reset_at' => $this->ai_credits_reset_at?->toIso8601String(),
        ];
    }
}
