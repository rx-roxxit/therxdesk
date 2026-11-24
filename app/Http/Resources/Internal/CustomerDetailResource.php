<?php

declare(strict_types=1);

namespace App\Http\Resources\Internal;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;
        $account = $data['account'];
        $usage = $data['storage'];

        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);
        $priceRow = $account->plan?->prices?->firstWhere('stripe_price_id', $subscription?->stripe_price);
        $offer = $account->latestRedeemedOffer();

        return [
            'id' => $account->id,
            'name' => $account->name,
            'created_at' => $account->created_at,
            'owner' => $account->owner ? [
                'id' => $account->owner->id,
                'name' => $account->owner->name,
                'email' => $account->owner->email,
                'email_verified_at' => $account->owner->email_verified_at,
            ] : null,
            'access_state' => [
                'has_app_access' => $account->hasAppAccess(),
                'lapsed' => $account->isLapsed(),
                'never_paid' => $account->isNeverPaid(),
                'suspended' => $account->isSuspended(),
                'suspended_at' => $account->suspended_at?->toIso8601String(),
                'suspension_reason' => $account->suspension_reason,
                'deletion_scheduled_at' => $account->deletion_scheduled_at?->toIso8601String(),
                'data_purged_at' => $account->data_purged_at?->toIso8601String(),
            ],
            'review_access' => $account->hasReviewAccess(),
            'plan' => $account->plan ? [
                'id' => $account->plan->id,
                'slug' => $account->plan->slug,
                'name' => $account->plan->name,
                'quota_bytes' => $account->plan->quota_bytes,
                'ai_credits_per_month' => $account->plan->ai_credits_per_month,
            ] : null,
            'subscription' => $subscription ? [
                'status' => $subscription->canceled() ? 'canceled' : $subscription->stripe_status,
                'interval' => $priceRow?->interval?->value,
                'amount' => $priceRow?->amount,
                'trial_ends_at' => $subscription->trial_ends_at,
                'ends_at' => $subscription->ends_at,
                'created_at' => $subscription->created_at,
            ] : null,
            'complimentary' => $account->hadComplimentaryPlan() ? [
                'in_force' => $account->isComplimentary(),
                'since' => $account->complimentary_since?->toIso8601String(),
                'until' => $account->complimentary_until?->toIso8601String(),
            ] : null,
            'offer' => $offer === null ? null : [
                'id' => $offer->id,
                'slug' => $offer->slug,
                'name' => $offer->name,
            ],
            'account_balance' => $data['account_balance'],
            'quota_bytes' => $account->quota_bytes === null ? null : (int) $account->quota_bytes,
            'refund' => $data['refund'],
            'storage' => [
                'used_bytes' => $usage->used,
                'quota_bytes' => $usage->limit,
            ],
            'members_count' => $account->users_count ?? $account->users()->count(),
            'recent_posts' => collect($data['recent_posts'])->map(fn ($post) => [
                'id' => $post->id,
                'platform' => $post->platform,
                'status' => $post->status,
                'publish_status' => $post->publish_status,
                'error_message' => $post->error_message,
                'scheduled_at' => $post->scheduled_at,
                'published_at' => $post->published_at,
                'created_at' => $post->created_at,
            ])->all(),
            'lifecycle_emails' => $account->lifecycleEmails
                ->sortByDesc('sent_at')
                ->values()
                ->map(fn ($email) => [
                    'type' => $email->type->value,
                    'user_id' => $email->user_id,
                    'sent_at' => $email->sent_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
