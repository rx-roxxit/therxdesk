<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Resources\Internal\CustomerDetailResource;
use App\Http\Resources\Internal\CustomerResource;
use App\Models\Account;
use App\Services\Storage\AccountStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountController
{
    public function __construct(private readonly AccountStorage $storage) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $term = trim((string) $request->query('search', ''));

        $accounts = Account::query()
            ->whereNotNull('owner_id')
            ->with(['owner', 'plan', 'subscriptions'])
            ->withCount('users')
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->whereHas('users', fn (Builder $users): Builder => $users->whereLike('email', "%{$term}%"));
            })
            ->latest()
            ->paginate((int) config('app.pagination.default'));

        return CustomerResource::collection($accounts);
    }

    public function show(string $account): CustomerDetailResource
    {
        $account = Account::query()
            ->with(['owner', 'plan.prices', 'subscriptions', 'lifecycleEmails'])
            ->withCount('users')
            ->findOrFail($account);

        $recentPosts = $account->posts()
            ->latest('posts.created_at')
            ->limit(10)
            ->get([
                'posts.id',
                'posts.platform',
                'posts.status',
                'posts.publish_status',
                'posts.error_message',
                'posts.scheduled_at',
                'posts.published_at',
                'posts.created_at',
            ]);

        return new CustomerDetailResource([
            'account' => $account,
            'recent_posts' => $recentPosts,
            'storage' => $this->storage->usage($account),
        ]);
    }
}
