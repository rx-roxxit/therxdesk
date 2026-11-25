<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\AiFeature;
use App\Exceptions\Ai\AiCreditsExhausted;
use App\Models\Account;
use App\Models\AiCreditUsage;
use App\Models\CatalogSetting;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
     * A reservation older than this belongs to a call that stopped before it
     * could reconcile, so it stops counting and the next call may spend.
     */
    private const int RESERVATION_MINUTES = 15;

    /**
     * The completion tokens a call is reserved for, before its real usage is
     * known. A caption, an idea and an alt text all fit well inside it.
     */
    private const int RESERVED_COMPLETION_TOKENS = 2048;

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
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('reserved_at')
                ->orWhere('reserved_at', '>=', now()->subMinutes(self::RESERVATION_MINUTES)))
            ->sum('credits');
    }

    public function metered(): bool
    {
        return ! config('trypost.self_hosted');
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

    /**
     * The credits a call reserves before it runs. The prompt is known then and
     * the completion is bounded above, so two calls can never both take the
     * last credit. The row is reconciled with the real usage afterwards.
     */
    public function estimate(AiFeature $feature, string $prompt = ''): int
    {
        $tokensPerCredit = max(1, CatalogSetting::current()->tokens_per_credit);
        $inputTokens = (int) ceil(mb_strlen($prompt) / 4);

        return max(1, (int) ceil(($inputTokens + self::RESERVED_COMPLETION_TOKENS) / $tokensPerCredit));
    }

    /**
     * Takes the call's worst-case credits under the Account's row lock, so a
     * parallel call cannot spend the same credit. When the month's last credits
     * are fewer than the estimate but at least one, the call keeps what is left
     * and reconciles to its real usage afterwards. A refused reservation raises
     * AiCreditsExhausted, which renders the same message the policy returns.
     */
    public function reserve(
        Account $account,
        ?User $user,
        ?Workspace $workspace,
        AiFeature $feature,
        int $credits,
    ): ?AiCreditUsage {
        if (! $this->metered()) {
            return null;
        }

        $credits = max(1, $credits);

        $reservation = DB::transaction(function () use ($account, $user, $workspace, $feature, $credits): ?AiCreditUsage {
            $locked = Account::query()->whereKey($account->id)->lockForUpdate()->first();

            if ($locked === null) {
                return null;
            }

            $available = $this->remaining($locked);

            if ($available < 1) {
                return null;
            }

            return AiCreditUsage::query()->create([
                'account_id' => $locked->id,
                'user_id' => $user?->id,
                'workspace_id' => $workspace?->id,
                'feature' => $feature,
                'model' => null,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'credits' => min($credits, $available),
                'reserved_at' => now(),
            ]);
        });

        if ($reservation === null) {
            $owner = $user === null || $user->id === $account->owner_id;

            throw new AiCreditsExhausted(
                $this->denialMessage($account, $user),
                $owner ? self::DENIAL_OWNER : self::DENIAL_MEMBER,
            );
        }

        return $reservation;
    }

    /**
     * Replaces the reservation with what the call really used. Self-hosted
     * accounts have no reservation and nothing to reconcile.
     */
    public function reconcile(?AiCreditUsage $reservation, Usage $usage, ?string $model = null): ?AiCreditUsage
    {
        if ($reservation === null) {
            return null;
        }

        $total = $usage->promptTokens + $usage->completionTokens;

        $reservation->forceFill([
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'total_tokens' => $total,
            'credits' => $this->creditsFor($reservation->feature, $total),
            'model' => $model,
            'reserved_at' => null,
        ])->save();

        return $reservation;
    }

    /**
     * The call never used its reservation (it failed), so the credits go back.
     */
    public function release(?AiCreditUsage $reservation): void
    {
        if ($reservation === null) {
            return;
        }

        $reservation->delete();
    }

    /**
     * The denial the Owner and a Member see when the month's credits are gone.
     */
    public function denialMessage(Account $account, ?User $user = null): string
    {
        if ($user !== null && $user->id !== $account->owner_id) {
            return __('rxdesk.ai_credits.exhausted_member');
        }

        return __('rxdesk.ai_credits.exhausted_owner', [
            'date' => $this->renewsAt($account)
                ->timezone($user?->timezone ?: config('app.timezone'))
                ->locale(app()->getLocale())
                ->isoFormat('LL'),
        ]);
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
        $credits = $this->creditsFor($feature, $total);

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

    private function creditsFor(AiFeature $feature, int $totalTokens): int
    {
        if (! $feature->spendsCredits() || $totalTokens <= 0) {
            return 0;
        }

        $tokensPerCredit = max(1, CatalogSetting::current()->tokens_per_credit);

        return (int) ceil($totalTokens / $tokensPerCredit);
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
