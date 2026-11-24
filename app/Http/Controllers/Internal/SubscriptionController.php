<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Resources\Internal\SubscriptionRowResource;
use App\Models\Account;
use App\Models\Subscription;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController
{
    public function index(): AnonymousResourceCollection
    {
        $subscriptions = Subscription::query()
            ->where('type', Account::SUBSCRIPTION_NAME)
            ->with(['account.owner', 'account.plan.prices'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) config('app.pagination.default'));

        return SubscriptionRowResource::collection($subscriptions);
    }
}
