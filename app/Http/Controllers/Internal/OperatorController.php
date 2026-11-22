<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\StoreOperatorRequest;
use App\Http\Requests\Internal\UpdateOperatorRequest;
use App\Http\Resources\Internal\OperatorOverviewResource;
use App\Models\Operator;
use App\Services\Operator\OperatorCatalog;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one Operator, as the admin app reads and writes it. There is no id in
 * the URL: the endpoints always act on the current row.
 */
class OperatorController
{
    public function __construct(private readonly OperatorCatalog $operators) {}

    public function show(): OperatorOverviewResource
    {
        return $this->overview();
    }

    public function store(StoreOperatorRequest $request): JsonResponse
    {
        $this->operators->create($request->validated(), AuthenticateInternalApi::actor($request));

        return $this->overview()
            ->response($request)
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateOperatorRequest $request): OperatorOverviewResource
    {
        $this->operators->updateCurrent($request->validated(), AuthenticateInternalApi::actor($request));

        return $this->overview();
    }

    private function overview(): OperatorOverviewResource
    {
        return new OperatorOverviewResource(Operator::current(), Operator::history());
    }
}
