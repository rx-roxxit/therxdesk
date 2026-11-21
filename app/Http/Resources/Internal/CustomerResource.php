<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $account = $this->resource;

        return [
            'id' => $account->id,
            'name' => $account->name,
            'owner' => $account->owner ? [
                'id' => $account->owner->id,
                'name' => $account->owner->name,
                'email' => $account->owner->email,
            ] : null,
            'plan' => $account->plan ? [
                'id' => $account->plan->id,
                'slug' => $account->plan->slug,
                'name' => $account->plan->name,
            ] : null,
            'subscription_status' => $account->subscriptionStatus(),
            'members_count' => $account->users_count ?? $account->users()->count(),
            'created_at' => $account->created_at,
        ];
    }
}
