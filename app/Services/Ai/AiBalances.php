<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Account;
use Illuminate\Support\Collection;

/**
 * Every Account's current AI balance, one row each, computed with the same
 * AiCredits service the customer sees. The period depends on the Trial, so a
 * row is only true for the moment it is built; the endpoint paginates the
 * whole list in memory.
 */
final class AiBalances
{
    public function __construct(private readonly AiCredits $credits) {}

    /**
     * @return Collection<int, array{account: Account, monthly_credits: int, used: int, remaining: int, renews_at: string, reset_at: string|null}>
     */
    public function rows(): Collection
    {
        $accounts = Account::query()
            ->whereNotNull('owner_id')
            ->with(['owner', 'plan', 'subscriptions'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $accounts->map(function (Account $account): array {
            $allowance = $this->credits->allowance($account);
            $used = $this->credits->used($account);

            return [
                'account' => $account,
                'monthly_credits' => $allowance,
                'used' => $used,
                'remaining' => max(0, $allowance - $used),
                'renews_at' => $this->credits->renewsAt($account)->toIso8601String(),
                'reset_at' => $account->ai_credits_reset_at?->toIso8601String(),
            ];
        });
    }
}
