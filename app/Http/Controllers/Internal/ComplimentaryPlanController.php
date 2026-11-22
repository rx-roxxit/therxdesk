<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\EndComplimentaryPlanRequest;
use App\Http\Requests\Internal\StoreComplimentaryPlanRequest;
use App\Http\Resources\Internal\ComplimentaryPlanResource;
use App\Models\Account;
use App\Models\Plan;
use App\Services\Billing\ComplimentaryPlans;
use Illuminate\Support\Carbon;

class ComplimentaryPlanController
{
    public function __construct(private readonly ComplimentaryPlans $complimentary) {}

    public function store(StoreComplimentaryPlanRequest $request, string $account): ComplimentaryPlanResource
    {
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        return $this->resource($this->complimentary->give(
            $this->account($account),
            $plan,
            $this->endsAt($request->validated('ends_at')),
            AuthenticateInternalApi::actor($request),
        ));
    }

    public function end(EndComplimentaryPlanRequest $request, string $account): ComplimentaryPlanResource
    {
        return $this->resource($this->complimentary->end(
            $this->account($account),
            $this->endsAt($request->validated('ends_at')),
            AuthenticateInternalApi::actor($request),
        ));
    }

    /**
     * The internal group has no SubstituteBindings middleware, so the
     * controller resolves the id itself.
     */
    private function account(string $id): Account
    {
        return Account::query()->findOrFail($id);
    }

    private function endsAt(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    private function resource(Account $account): ComplimentaryPlanResource
    {
        return new ComplimentaryPlanResource($account->loadMissing('plan'));
    }
}
