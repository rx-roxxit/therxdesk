<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\QuotaCutDryRunRequest;
use App\Http\Requests\Internal\StorePlanRequest;
use App\Http\Requests\Internal\UpdatePlanRequest;
use App\Http\Resources\Internal\PlanAdminResource;
use App\Models\Plan;
use App\Services\Catalog\Catalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PlanController
{
    public function __construct(private readonly Catalog $catalog) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $term = trim((string) $request->query('search', ''));
        $archived = $this->archivedFilter($request);

        $plans = Plan::query()
            ->with('prices')
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereLike('name', "%{$term}%")
                        ->orWhereLike('slug', "%{$term}%");
                });
            })
            ->when($archived !== null, function (Builder $query) use ($archived): void {
                $query->where('is_archived', $archived);
            })
            ->orderBy('sort')
            ->orderBy('name')
            ->paginate((int) config('app.pagination.default'));

        return PlanAdminResource::collection($plans);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->catalog->create($request->validated(), AuthenticateInternalApi::actor($request));

        return (new PlanAdminResource($plan->load('prices')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdatePlanRequest $request, string $plan): PlanAdminResource
    {
        $plan = $this->catalog->update($this->plan($plan), $request->validated(), AuthenticateInternalApi::actor($request));

        return new PlanAdminResource($plan->load('prices'));
    }

    public function archive(Request $request, string $plan): PlanAdminResource
    {
        $plan = $this->catalog->archive($this->plan($plan), AuthenticateInternalApi::actor($request));

        return new PlanAdminResource($plan->load('prices'));
    }

    public function quotaCutDryRun(QuotaCutDryRunRequest $request, string $plan): JsonResponse
    {
        $quotaBytes = (int) $request->validated('quota_bytes');

        return response()->json([
            'quota_bytes' => $quotaBytes,
            'owners' => $this->catalog->quotaCutOwners($this->plan($plan), $quotaBytes),
        ]);
    }

    /**
     * The Plan of the route: the internal group has no SubstituteBindings
     * middleware, so the controller resolves the id itself.
     */
    private function plan(string $id): Plan
    {
        return Plan::query()->findOrFail($id);
    }

    /**
     * `?archived=1` lists archived Plans, `0` the active ones, and anything
     * else (the default) lists both.
     */
    private function archivedFilter(Request $request): ?bool
    {
        $value = $request->query('archived');

        if (in_array($value, ['1', 'true'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false'], true)) {
            return false;
        }

        return null;
    }
}
