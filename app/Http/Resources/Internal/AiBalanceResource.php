<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Account's AI balance row, as built by AiBalances.
 */
class AiBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = data_get($this->resource, 'account');
        $owner = $account->owner;
        $plan = $account->plan;

        return [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'owner' => $owner ? [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
            ] : null,
            'plan' => $plan ? [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
            ] : null,
            'ai_credits_per_month' => $account->ai_credits_per_month,
            'monthly_credits' => (int) data_get($this->resource, 'monthly_credits'),
            'used' => (int) data_get($this->resource, 'used'),
            'remaining' => (int) data_get($this->resource, 'remaining'),
            'renews_at' => data_get($this->resource, 'renews_at'),
            'reset_at' => data_get($this->resource, 'reset_at'),
        ];
    }
}
