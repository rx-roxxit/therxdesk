<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Ai\ResetAccountAiCredits;
use App\Actions\Ai\UpdateAccountAiCredits;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\UpdateAccountAiCreditsRequest;
use App\Http\Resources\Internal\AccountAiCreditsResource;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountAiCreditsController
{
    public function __construct(
        private readonly UpdateAccountAiCredits $update,
        private readonly ResetAccountAiCredits $reset,
    ) {}

    public function update(UpdateAccountAiCreditsRequest $request, string $account): AccountAiCreditsResource
    {
        $value = $request->validated('ai_credits_per_month');

        return $this->resource($this->update->execute(
            $this->account($account),
            $value === null ? null : (int) $value,
            AuthenticateInternalApi::actor($request),
        ));
    }

    public function reset(Request $request, string $account): AccountAiCreditsResource
    {
        return $this->resource($this->reset->execute(
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

    private function resource(Account $account): AccountAiCreditsResource
    {
        return new AccountAiCreditsResource($account);
    }
}
