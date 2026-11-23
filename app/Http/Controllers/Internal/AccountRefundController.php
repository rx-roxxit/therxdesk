<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Billing\RefundAndCancel;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Resources\Internal\RefundAndCancelResource;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountRefundController
{
    public function __construct(private readonly RefundAndCancel $refundAndCancel) {}

    public function store(Request $request, string $account): RefundAndCancelResource
    {
        return new RefundAndCancelResource($this->refundAndCancel->execute(
            Account::query()->findOrFail($account),
            AuthenticateInternalApi::actor($request),
        ));
    }
}
