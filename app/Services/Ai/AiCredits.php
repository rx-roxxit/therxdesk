<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\AiFeature;
use App\Models\Account;
use App\Models\AiCreditUsage;
use App\Models\CatalogSetting;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\Usage;
use Throwable;

/**
 * The monthly AI credit balance of an Account. The Plan (or a Trial's fixed
 * amount, or an admin override) is the allowance; the sum of this month's
 * ledger rows is the use. The month is a calendar month in UTC with no
 * carry-over, except during a Trial, which lasts its whole length.
 */
final class AiCredits
{
    public const string DENIAL_OWNER = 'ai_credits_exhausted_owner';

    public const string DENIAL_MEMBER = 'ai_credits_exhausted_member';

    /**
     * @return array{used: int, remaining: int, total: int, low: bool, percent: int, renews_at: string}|null
     */
    public function payload(Account $account): ?array
    {
        if (config('trypost.self_hosted')) {
            return null;
        }

        $total = $this->allowance($account);
        $used = $this->used($account);
        $remaining = max(0, $total - $used);

        return [
            'used' => $used,
            'remaining' => $remaining,
            'total' => $total,
            'low' => $this->isLow($remaining, $total),
            'percent' => $this->percentOf($used, $total),
            'renews_at' => $this->renewsAt($account)->toIso8601String(),
        ];
    }

    public function allowance(Account $account): int
    {
        if ($account->isOnTrial()) {
            return (int) CatalogSetting::current()->trial_ai_credits;
        }

        return $account->aiCreditsPerMonth() ?? 0;
    }

    public function periodStart(Account $account): CarbonImmutable
    {
        if (! $account->isOnTrial()) {
            return CarbonImmutable::now('UTC')->startOfMonth();
        }

        $subscription = $account->subscription(Account::SUBSCRIPTION_NAME);

        if ($subscription?->onTrial()) {
            $start = $subscription->trial_started_at ?? $subscription->created_at;

            return CarbonImmutable::parse($start)->utc();
        }

        $days = (int) CatalogSetting::current()->trial_days;

        return CarbonImmutable::parse($account->trial_ends_at)->utc()->subDays($days);
    }

    public function used(Account $account): int
    {
        $since = $this->periodStart($account);
        $resetAt = $account->ai_credits_reset_at;

        if ($resetAt !== null && $resetAt->greaterThan($since)) {
            $since = CarbonImmutable::parse($resetAt)->utc();
        }

        return (int) AiCreditUsage::query()
            ->where('account_id', $account->id)
            ->where('created_at', '>=', $since)
            ->sum('credits');
    }

    public function remaining(Account $account): int
    {
        return max(0, $this->allowance($account) - $this->used($account));
    }

    public function canSpend(Account $account): bool
    {
        if (config('trypost.self_hosted')) {
            return true;
        }

        return $this->remaining($account) >= 1;
    }

    public function low(Account $account): bool
    {
        return $this->isLow($this->remaining($account), $this->allowance($account));
    }

    public function renewsAt(Account $account): CarbonImmutable
    {
        if ($account->isOnTrial()) {
            $ends = $account->activeTrialEndsAt();

            if ($ends !== null) {
                return CarbonImmutable::parse($ends)->utc();
            }
        }

        return CarbonImmutable::now('UTC')->addMonthNoOverflow()->startOfMonth();
    }

    public function charge(
        Account $account,
        ?User $user,
        ?Workspace $workspace,
        AiFeature $feature,
        Usage $usage,
        ?string $model = null,
    ): ?AiCreditUsage {
        if (config('trypost.self_hosted')) {
            return null;
        }

        return $this->record(
            $account,
            $user,
            $workspace,
            $feature,
            $usage->promptTokens,
            $usage->completionTokens,
            $model,
        );
    }

    /**
     * Write one ledger row, always: operator work such as Ask the Desk has no
     * Account, but its tokens still belong in the one ledger.
     */
    public function record(
        ?Account $account,
        ?User $user,
        ?Workspace $workspace,
        AiFeature $feature,
        int $promptTokens,
        int $completionTokens,
        ?string $model = null,
    ): ?AiCreditUsage {
        $total = $promptTokens + $completionTokens;

        $credits = 0;

        if ($feature->spendsCredits() && $total > 0) {
            $tokensPerCredit = max(1, CatalogSetting::current()->tokens_per_credit);
            $credits = (int) ceil($total / $tokensPerCredit);
        }

        try {
            return AiCreditUsage::query()->create([
                'account_id' => $account?->id,
                'user_id' => $user?->id,
                'workspace_id' => $workspace?->id,
                'feature' => $feature,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $total,
                'credits' => $credits,
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to record AI credit usage.', [
                'account_id' => $account?->id,
                'feature' => $feature->value,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function isLow(int $remaining, int $total): bool
    {
        return $total > 0 && $remaining < $total * 0.1;
    }

    private function percentOf(int $used, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return min(100, (int) round($used / $total * 100));
    }
}
