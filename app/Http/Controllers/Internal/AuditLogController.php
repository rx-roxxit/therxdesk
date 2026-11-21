<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Resources\Internal\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = AuditLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) config('app.pagination.default'));

        return AuditLogResource::collection($logs);
    }
}
