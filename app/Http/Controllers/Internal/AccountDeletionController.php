<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Account\DeleteAccountNow;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\DeleteAccountRequest;
use App\Http\Resources\Internal\AccountDeletionResource;
use App\Models\Account;

class AccountDeletionController
{
    public function __construct(private readonly DeleteAccountNow $delete) {}

    public function store(DeleteAccountRequest $request, string $account): AccountDeletionResource
    {
        $model = Account::query()->with('owner')->findOrFail($account);

        $kind = $this->delete->execute(
            $model,
            (string) $request->validated('confirm_email'),
            AuthenticateInternalApi::actor($request),
        );

        return new AccountDeletionResource([
            'id' => $model->id,
            'deleted' => true,
            'kind' => $kind,
        ]);
    }
}
