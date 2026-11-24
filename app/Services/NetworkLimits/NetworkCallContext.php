<?php

declare(strict_types=1);

namespace App\Services\NetworkLimits;

use App\Models\SocialAccount;

/**
 * The Account behind the calls being made right now. The HTTP client has no
 * request context of its own, so the entry points that know their Account set
 * it here around their work; jobs and services that do not set it still count
 * the app-wide bucket, just not the Account's share.
 */
class NetworkCallContext
{
    private ?string $accountId = null;

    public function run(?string $accountId, callable $callback): mixed
    {
        $previous = $this->accountId;
        $this->accountId = $accountId;

        try {
            return $callback();
        } finally {
            $this->accountId = $previous;
        }
    }

    public function currentAccountId(): ?string
    {
        return $this->accountId;
    }

    public static function accountIdFor(SocialAccount $account): string
    {
        return (string) $account->workspace->account_id;
    }
}
