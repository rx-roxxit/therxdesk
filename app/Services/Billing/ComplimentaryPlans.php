<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Actions\Audit\RecordAuditEntry;
use App\Models\Account;
use App\Models\Plan;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * The Complimentary Plan the System admin gives an Account outside Stripe
 * (ADR 0006). Giving grants app access and the Plan's Quota and AI credits
 * through `accounts.plan_id`; ending it leaves the Account Lapsed. There is
 * no Stripe object and no invoice, and every write is audited.
 */
class ComplimentaryPlans
{
    /**
     * The notice the Terms promise when the System admin ends a Complimentary
     * Plan without a date.
     */
    public const int END_NOTICE_DAYS = 30;

    /**
     * Gives the Account a Plan, with or without an end date. Giving again
     * while one is in force updates the Plan and the date.
     */
    public function give(Account $account, Plan $plan, ?CarbonInterface $endsAt, string $actor): Account
    {
        if (filled($account->blocked_country_at)) {
            throw ValidationException::withMessages([
                'account' => 'A blocked Account cannot receive a Complimentary Plan.',
            ]);
        }

        if ($account->subscribed(Account::SUBSCRIPTION_NAME)) {
            throw ValidationException::withMessages([
                'account' => 'An Account with a Subscription cannot receive a Complimentary Plan.',
            ]);
        }

        $before = $this->snapshot($account);

        $account->forceFill([
            'plan_id' => $plan->id,
            'complimentary_since' => now(),
            'complimentary_until' => $endsAt,
        ])->save();

        RecordAuditEntry::execute($actor, 'account.complimentary.give', $account, $before, $this->snapshot($account));

        return $account->fresh();
    }

    /**
     * Ends a Complimentary Plan, at once when the date has passed. Without a
     * date it ends 30 days from now, the notice the Terms promise.
     */
    public function end(Account $account, ?CarbonInterface $endsAt, string $actor): Account
    {
        if (blank($account->complimentary_since)) {
            throw ValidationException::withMessages([
                'account' => 'This Account has no Complimentary Plan.',
            ]);
        }

        $endsAt ??= now()->addDays(self::END_NOTICE_DAYS);

        $before = $this->snapshot($account);

        $account->forceFill(['complimentary_until' => $endsAt])->save();

        RecordAuditEntry::execute($actor, 'account.complimentary.end', $account, $before, $this->snapshot($account));

        return $account->fresh();
    }

    /**
     * @return array{plan_id: string|null, complimentary_since: string|null, complimentary_until: string|null}
     */
    private function snapshot(Account $account): array
    {
        return [
            'plan_id' => $account->plan_id,
            'complimentary_since' => $account->complimentary_since?->toIso8601String(),
            'complimentary_until' => $account->complimentary_until?->toIso8601String(),
        ];
    }
}
