<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Resources\Internal\SitePlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController
{
    public function __invoke(Request $request): JsonResponse
    {
        $plans = Plan::query()
            ->active()
            ->orderBy('sort')
            ->orderBy('name')
            ->with('prices')
            ->get();

        return response()->json([
            'plans' => SitePlanResource::collection($plans)->resolve($request),
        ]);
    }
}
