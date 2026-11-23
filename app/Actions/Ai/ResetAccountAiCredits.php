<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Actions\Audit\RecordAuditEntry;
use App\Models\Account;
use App\Services\Ai\AiCredits;

/**
 * The ledger stays intact; usage written before the reset instant is simply
 * no longer counted.
 */
class ResetAccountAiCredits
{
    public function execute(Account $account, string $actor): Account
    {
        $before = [
            'ai_credits_reset_at' => $account->ai_credits_reset_at?->toIso8601String(),
            'used' => app(AiCredits::class)->used($account),
        ];

        $account->forceFill(['ai_credits_reset_at' => now('UTC')])->save();

        RecordAuditEntry::execute($actor, 'account.ai-credits.reset', $account, $before, [
            'ai_credits_reset_at' => $account->ai_credits_reset_at?->toIso8601String(),
        ]);

        return $account->fresh();
    }
}
