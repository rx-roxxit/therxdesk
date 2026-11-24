<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the admin app's Subscriptions list: the Account, its Owner, the
 * Plan and price the Subscription bills, and the Offer it was bought with.
 *
 * @mixin Subscription
 */
class SubscriptionRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = $this->account;
        $price = $account?->plan?->prices?->firstWhere('stripe_price_id', $this->stripe_price);
        $offer = $account?->latestRedeemedOffer();

        return [
            'account' => $account === null ? null : [
                'id' => $account->id,
                'name' => $account->name,
            ],
            'owner' => $account?->owner === null ? null : [
                'email' => $account->owner->email,
            ],
            'plan_name' => $account?->plan?->name,
            'interval' => $price?->interval?->value,
            'amount' => $price?->amount,
            'status' => $this->canceled() ? 'canceled' : $this->stripe_status,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'offer' => $offer === null ? null : [
                'id' => $offer->id,
                'slug' => $offer->slug,
                'name' => $offer->name,
            ],
        ];
    }
}
