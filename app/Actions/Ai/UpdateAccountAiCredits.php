<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Actions\Audit\RecordAuditEntry;
use App\Models\Account;

/**
 * A null clears the override, so the Plan's amount applies again.
 */
class UpdateAccountAiCredits
{
    public function execute(Account $account, ?int $creditsPerMonth, string $actor): Account
    {
        $before = $this->snapshot($account);

        $account->forceFill(['ai_credits_per_month' => $creditsPerMonth])->save();

        RecordAuditEntry::execute($actor, 'account.ai-credits.override', $account, $before, $this->snapshot($account));

        return $account->fresh();
    }

    /**
     * @return array{ai_credits_per_month: int|null}
     */
    private function snapshot(Account $account): array
    {
        return ['ai_credits_per_month' => $account->ai_credits_per_month];
    }
}
