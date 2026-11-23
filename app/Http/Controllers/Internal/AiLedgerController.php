<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Resources\Internal\AiLedgerResource;
use App\Models\AiCreditUsage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AiLedgerController
{
    public function index(): AnonymousResourceCollection
    {
        $usages = AiCreditUsage::query()
            ->with(['account.owner', 'user', 'workspace'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) config('app.pagination.default'));

        return AiLedgerResource::collection($usages);
    }
}
