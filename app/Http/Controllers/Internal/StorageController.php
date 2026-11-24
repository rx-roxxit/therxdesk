<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Resources\Internal\StorageRowResource;
use App\Models\Account;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StorageController
{
    public function index(): AnonymousResourceCollection
    {
        $accounts = Account::query()
            ->whereNotNull('owner_id')
            ->whereHas('workspaces')
            ->with(['owner', 'plan'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) config('app.pagination.default'));

        return StorageRowResource::collection($accounts);
    }
}
