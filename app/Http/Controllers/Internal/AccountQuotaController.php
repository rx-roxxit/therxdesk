<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Account\UpdateAccountQuota;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\UpdateAccountQuotaRequest;
use App\Http\Resources\Internal\AccountQuotaResource;
use App\Models\Account;

class AccountQuotaController
{
    public function __construct(private readonly UpdateAccountQuota $update) {}

    public function update(UpdateAccountQuotaRequest $request, string $account): AccountQuotaResource
    {
        $value = $request->validated('quota_bytes');

        return new AccountQuotaResource($this->update->execute(
            Account::query()->findOrFail($account),
            $value === null ? null : (int) $value,
            AuthenticateInternalApi::actor($request),
        ));
    }
}
