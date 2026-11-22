<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class ComplimentaryPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => $this->plan ? [
                'id' => $this->plan->id,
                'slug' => $this->plan->slug,
                'name' => $this->plan->name,
            ] : null,
            'complimentary' => [
                'since' => $this->complimentary_since?->toIso8601String(),
                'until' => $this->complimentary_until?->toIso8601String(),
                'in_force' => $this->isComplimentary(),
            ],
        ];
    }
}
