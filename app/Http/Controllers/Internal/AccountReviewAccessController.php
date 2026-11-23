<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Audit\RecordAuditEntry;
use App\Http\Middleware\Internal\AuthenticateInternalApi;
use App\Http\Requests\Internal\UpdateReviewAccessRequest;
use App\Http\Resources\Internal\AccountReviewAccessResource;
use App\Models\Account;

/**
 * The System admin's per-Account Review access switch. An Account with it may
 * connect and publish to networks that are installed but switched off; only a
 * network the server has no credentials for stays unusable. Every flip is
 * audited, whatever new signups are doing.
 */
class AccountReviewAccessController
{
    public function update(UpdateReviewAccessRequest $request, string $account): AccountReviewAccessResource
    {
        $account = Account::query()->findOrFail($account);
        $enabled = $request->boolean('enabled');
        $before = $account->hasReviewAccess();

        $account->forceFill(['review_access' => $enabled])->save();

        RecordAuditEntry::execute(
            AuthenticateInternalApi::actor($request),
            $enabled ? 'account.review_access.enable' : 'account.review_access.disable',
            $account,
            ['review_access' => $before],
            ['review_access' => $enabled],
        );

        return new AccountReviewAccessResource($account->fresh());
    }
}
