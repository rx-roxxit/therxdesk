<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Account\LiftSuspension;
use App\Actions\Account\SuspendAccount;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\SuspendAccountRequest;
use App\Http\Resources\Internal\AccountSuspensionResource;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountSuspensionController
{
    public function __construct(
        private readonly SuspendAccount $suspend,
        private readonly LiftSuspension $lift,
    ) {}

    public function store(SuspendAccountRequest $request, string $account): AccountSuspensionResource
    {
        return $this->resource($this->suspend->execute(
            $this->account($account),
            (string) $request->validated('reason'),
            AuthenticateInternalApi::actor($request),
        ));
    }

    public function lift(Request $request, string $account): AccountSuspensionResource
    {
        return $this->resource($this->lift->execute(
            $this->account($account),
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

    private function resource(Account $account): AccountSuspensionResource
    {
        return new AccountSuspensionResource($account);
    }
}
