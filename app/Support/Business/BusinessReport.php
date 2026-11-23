<?php

declare(strict_types=1);

namespace App\Support\Business;

use App\Enums\Billing\BusinessStatus;
use App\Enums\Billing\OfferReach;
use App\Models\Account;
use App\Models\BusinessSnapshot;
use App\Models\Offer;
use App\Models\Subscription;
use App\Support\SupportChat\SupportChatCounters;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds the `/internal/v1/business` payload from our own database. Today's
 * figures are computed live through the same state resolver the snapshot job
 * uses; the waterfall and churn read the snapshots. Nothing in the payload
 * names a customer, an email, a post or a transcript: counts and money only.
 */
final class BusinessReport
{
    /**
     * The daily job runs every night, so the state at the start of a window is
     * found in the last few weeks of snapshots even for the 12-month window.
     */
    private const int BASELINE_LOOKBACK_DAYS = 62;

    /**
     * @var list<int>
     */
    private const array WINDOWS = [30, 90, 365];

    public function __construct(
        private readonly BusinessStateResolver $resolver,
        private readonly SupportChatCounters $supportChat,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonInterface $at): array
    {
        $live = $this->liveStates($at);
        $today = CarbonImmutable::parse($at->toDateString(), 'UTC')->startOfDay();

        $waterfall = [];
        $churn = [];
        $conversion = [];

        foreach (self::WINDOWS as $days) {
            $start = $today->subDays($days);
            $baselines = $this->baselines($start);
            $snapshots = $this->snapshotsByAccount($start, $today);

            $waterfall[(string) $days] = $this->waterfall($today, $start, $baselines, $snapshots, $live);
            $churn[(string) $days] = $this->churn($today, $start, $baselines, $snapshots, $live);
            $conversion[(string) $days] = $this->trialConversion($start, $today);
        }

        return [
            'generated_at' => $at->toIso8601String(),
            'today' => $today->toDateString(),
            'current' => $this->current($live),
            'waterfall' => $waterfall,
            'churn' => $churn,
            'trial_conversion' => $conversion,
            'offers' => $this->offers($today, $live),
            'support_chat' => $this->supportChat($today),
        ];
    }

    /**
     * @return array<string, AccountBusinessState>
     */
    private function liveStates(CarbonInterface $at): array
    {
        $states = [];

        $this->resolver->accounts()->chunkById(500, function (Collection $accounts) use ($at, &$states): void {
            foreach ($accounts as $account) {
                $state = $this->resolver->stateFor($account, $at);
                $states[$state->accountId] = $state;
            }
        });

        return $states;
    }

    /**
     * The window's snapshots, one collection per Account, oldest first.
     *
     * @return array<string, Collection<int, BusinessSnapshot>>
     */
    private function snapshotsByAccount(CarbonImmutable $start, CarbonImmutable $today): array
    {
        return BusinessSnapshot::query()
            ->whereBetween('date', [$start->toDateString(), $today->toDateString()])
            ->orderBy('date')
            ->get()
            ->groupBy('account_id')
            ->all();
    }

    /**
     * The last snapshot on or before the window's first day, one per Account.
     *
     * @return array<string, BusinessSnapshot>
     */
    private function baselines(CarbonImmutable $start): array
    {
        return BusinessSnapshot::query()
            ->whereBetween('date', [$start->subDays(self::BASELINE_LOOKBACK_DAYS)->toDateString(), $start->toDateString()])
            ->orderByDesc('date')
            ->get()
            ->unique('account_id')
            ->keyBy('account_id')
            ->all();
    }

    /**
     * @param  array<string, BusinessSnapshot>  $baselines
     * @param  array<string, Collection<int, BusinessSnapshot>>  $snapshots
     * @param  array<string, AccountBusinessState>  $live
     * @return array<string, mixed>
     */
    private function waterfall(
        CarbonImmutable $today,
        CarbonImmutable $start,
        array $baselines,
        array $snapshots,
        array $live,
    ): array {
        $new = $this->bucket();
        $expansion = $this->bucket();
        $contraction = $this->bucket();
        $churn = $this->bucket();

        foreach ($this->accountsInPlay($baselines, $snapshots, $live) as $accountId) {
            $baseline = $baselines[$accountId] ?? null;
            $end = $this->endRow($today, $accountId, $live, $snapshots);

            if ($end === null) {
                continue;
            }

            if ($this->isSuspended($baseline) || $this->isSuspendedRow($end)) {
                continue;
            }

            $from = $baseline?->mrr_cents ?? 0;
            $to = $end['mrr_cents'];

            if ($from === $to) {
                continue;
            }

            if ($from === 0) {
                $this->add($new, 1, $to);

                continue;
            }

            if ($to === 0) {
                $this->add($churn, 1, -$from);

                continue;
            }

            if ($to > $from) {
                $this->add($expansion, 1, $to - $from);

                continue;
            }

            $this->add($contraction, 1, $to - $from);
        }

        return [
            'start' => $start->toDateString(),
            'end' => $today->toDateString(),
            'new' => $new,
            'expansion' => $expansion,
            'contraction' => $contraction,
            'churn' => $churn,
        ];
    }

    /**
     * @param  array<string, BusinessSnapshot>  $baselines
     * @param  array<string, Collection<int, BusinessSnapshot>>  $snapshots
     * @param  array<string, AccountBusinessState>  $live
     * @return array<string, mixed>
     */
    private function churn(
        CarbonImmutable $today,
        CarbonImmutable $start,
        array $baselines,
        array $snapshots,
        array $live,
    ): array {
        $accounts = 0;
        $mrr = 0;
        $types = $this->zeroes(['voluntary', 'payment_failed', 'admin']);
        $reasons = [];

        foreach ($this->accountsInPlay($baselines, $snapshots, $live) as $accountId) {
            $rows = $this->history($today, $accountId, $baselines, $snapshots, $live);
            $previous = null;

            foreach ($rows as $row) {
                if ($previous !== null
                    && $previous['mrr_cents'] > 0
                    && $row['mrr_cents'] === 0
                    && $row['date'] >= $start->toDateString()
                    && ! $this->isSuspendedRow($previous)
                    && ! $this->isSuspendedRow($row)) {
                    $accounts++;
                    $mrr += $previous['mrr_cents'];

                    $type = $row['cancellation_type'];

                    if (filled($type)) {
                        $types[$type] = ($types[$type] ?? 0) + 1;
                    }

                    $reason = $row['cancellation_reason'];

                    if (filled($reason)) {
                        $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
                    }
                }

                $previous = $row;
            }
        }

        $payingAtStart = $this->payingAtStart($baselines);

        return [
            'start' => $start->toDateString(),
            'end' => $today->toDateString(),
            'accounts' => $accounts,
            'mrr_cents' => $mrr,
            'paying_at_start' => $payingAtStart,
            'rate' => $payingAtStart > 0 ? round($accounts / $payingAtStart, 4) : null,
            'types' => $types,
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trialConversion(CarbonImmutable $start, CarbonImmutable $today): array
    {
        $from = $start->startOfDay();
        $to = $today->endOfDay();

        $trials = Subscription::query()
            ->with('owner')
            ->whereNotNull('trial_started_at')
            ->whereNull('trial_skipped_at')
            ->whereBetween('trial_ended_at', [$from, $to])
            ->get();

        $ended = $trials->count();
        $converted = 0;
        $pending = 0;

        foreach ($trials as $subscription) {
            $account = $subscription->owner;

            if ($account !== null && filled($account->first_invoice_id)) {
                $converted++;

                continue;
            }

            if ($subscription->pastDue() && blank($account?->first_paid_at)) {
                $pending++;
            }
        }

        $paidWithoutTrial = Subscription::query()
            ->whereNotNull('paid_without_trial_at')
            ->whereBetween('paid_without_trial_at', [$from, $to])
            ->count();

        $denominator = $ended - $pending;

        return [
            'start' => $start->toDateString(),
            'end' => $today->toDateString(),
            'ended' => $ended,
            'converted' => $converted,
            'pending' => $pending,
            'not_converted' => $ended - $converted - $pending,
            'paid_without_trial' => $paidWithoutTrial,
            'rate' => $denominator > 0 ? round($converted / $denominator, 4) : null,
        ];
    }

    /**
     * @param  array<string, AccountBusinessState>  $live
     * @return array<string, mixed>
     */
    private function current(array $live): array
    {
        $mrr = 0;
        $paying = 0;
        $statuses = $this->zeroes(array_map(fn (BusinessStatus $status): string => $status->value, BusinessStatus::cases()));
        $plans = [];

        foreach ($live as $state) {
            $mrr += $state->mrrCents;

            if ($state->mrrCents > 0) {
                $paying++;
            }

            $statuses[$state->status->value]++;

            $key = $state->planId ?? 'none';
            $plans[$key] ??= [
                'plan_id' => $state->planId,
                'plan_name' => $state->planName,
                'accounts' => 0,
                'mrr_cents' => 0,
            ];

            $plans[$key]['accounts']++;
            $plans[$key]['mrr_cents'] += $state->mrrCents;
        }

        usort($plans, function (array $a, array $b): int {
            $byMrr = $b['mrr_cents'] <=> $a['mrr_cents'];

            if ($byMrr !== 0) {
                return $byMrr;
            }

            $byAccounts = $b['accounts'] <=> $a['accounts'];

            if ($byAccounts !== 0) {
                return $byAccounts;
            }

            return strcmp((string) $a['plan_name'], (string) $b['plan_name']);
        });

        return [
            'accounts' => count($live),
            'mrr_cents' => $mrr,
            'paying_accounts' => $paying,
            'status_counts' => $statuses,
            'plan_mix' => array_values($plans),
        ];
    }

    /**
     * @param  array<string, AccountBusinessState>  $live
     * @return list<array<string, mixed>>
     */
    private function offers(CarbonImmutable $today, array $live): array
    {
        $mrrByOffer = [];

        foreach ($live as $state) {
            if (filled($state->offerId)) {
                $mrrByOffer[$state->offerId] = ($mrrByOffer[$state->offerId] ?? 0) + $state->mrrCents;
            }
        }

        $checkouts = $this->successfulCheckouts($today);

        return Offer::query()
            ->orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Offer $offer) use ($today, $mrrByOffer, $checkouts): array {
                $redemptions = $offer->redemptions()->count();
                $inWindow = $offer->redemptions()
                    ->wherePivotBetween('redeemed_at', [$today->subDays(30)->startOfDay(), $today->endOfDay()])
                    ->count();

                $share = null;

                if ($offer->reach === OfferReach::General && $checkouts > 0) {
                    $share = round($inWindow / $checkouts, 4);
                }

                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'reach' => $offer->reach->value,
                    'starts_at' => $offer->starts_at,
                    'ends_at' => $offer->ends_at,
                    'ended' => $offer->hasEnded(),
                    'redemptions' => $redemptions,
                    'max_redemptions' => $offer->max_redemptions,
                    'redemptions_in_window' => $inWindow,
                    'checkouts_in_window' => $offer->reach === OfferReach::General ? $checkouts : null,
                    'share' => $share,
                    'mrr_cents' => $mrrByOffer[$offer->id] ?? 0,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function supportChat(CarbonImmutable $today): array
    {
        $start = $today->subDays(30);
        $days = $this->supportChat->between($start, $today);

        $columns = [
            'conversations_started',
            'user_messages',
            'handoffs',
            'feedback_up',
            'feedback_down',
            'prompt_tokens',
            'completion_tokens',
            'total_tokens',
            'list_cost_micros',
        ];

        $totals = [];

        foreach ($columns as $column) {
            $totals[$column] = (int) $days->sum($column);
        }

        return [
            'start' => $start->toDateString(),
            'end' => $today->toDateString(),
            'days' => $days->map(fn ($day): array => [
                'date' => $day->date->toDateString(),
                ...array_combine($columns, array_map(fn (string $column): int => (int) $day->{$column}, $columns)),
            ])->all(),
            'totals' => $totals,
        ];
    }

    private function successfulCheckouts(CarbonImmutable $today): int
    {
        return Account::query()
            ->whereBetween('checkout_completed_at', [$today->subDays(30)->startOfDay(), $today->endOfDay()])
            ->count();
    }

    /**
     * Every Account a window can mention: one with a baseline, one with a
     * snapshot in the window, or one live today.
     *
     * @param  array<string, BusinessSnapshot>  $baselines
     * @param  array<string, Collection<int, BusinessSnapshot>>  $snapshots
     * @param  array<string, AccountBusinessState>  $live
     * @return list<string>
     */
    private function accountsInPlay(array $baselines, array $snapshots, array $live): array
    {
        return collect(array_keys($baselines))
            ->merge(array_keys($snapshots))
            ->merge(array_keys($live))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, AccountBusinessState>  $live
     * @param  array<string, Collection<int, BusinessSnapshot>>  $snapshots
     * @return array<string, mixed>|null
     */
    private function endRow(CarbonImmutable $today, string $accountId, array $live, array $snapshots): ?array
    {
        $state = $live[$accountId] ?? null;

        if ($state !== null) {
            return $this->liveRow($today, $state);
        }

        $row = ($snapshots[$accountId] ?? collect())->last();

        if ($row === null) {
            return null;
        }

        return $this->snapshotRow($row);
    }

    /**
     * @param  array<string, BusinessSnapshot>  $baselines
     * @param  array<string, Collection<int, BusinessSnapshot>>  $snapshots
     * @param  array<string, AccountBusinessState>  $live
     * @return list<array<string, mixed>>
     */
    private function history(
        CarbonImmutable $today,
        string $accountId,
        array $baselines,
        array $snapshots,
        array $live,
    ): array {
        $rows = [];

        $baseline = $baselines[$accountId] ?? null;

        if ($baseline !== null) {
            $rows[$baseline->date->toDateString()] = $this->snapshotRow($baseline);
        }

        foreach ($snapshots[$accountId] ?? [] as $snapshot) {
            $rows[$snapshot->date->toDateString()] = $this->snapshotRow($snapshot);
        }

        $state = $live[$accountId] ?? null;

        if ($state !== null) {
            $rows[$today->toDateString()] = $this->liveRow($today, $state);
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * @param  array<string, BusinessSnapshot>  $baselines
     */
    private function payingAtStart(array $baselines): int
    {
        return collect($baselines)
            ->reject(fn (BusinessSnapshot $row): bool => $this->isSuspended($row))
            ->where('mrr_cents', '>', 0)
            ->count();
    }

    private function isSuspended(?BusinessSnapshot $row): bool
    {
        return $row?->status === BusinessStatus::Suspended;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isSuspendedRow(array $row): bool
    {
        return $row['status'] === BusinessStatus::Suspended->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotRow(BusinessSnapshot $row): array
    {
        return [
            'date' => $row->date->toDateString(),
            'mrr_cents' => $row->mrr_cents,
            'status' => $row->status->value,
            'cancellation_type' => $row->cancellation_type?->value,
            'cancellation_reason' => $row->cancellation_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function liveRow(CarbonImmutable $today, AccountBusinessState $state): array
    {
        return [
            'date' => $today->toDateString(),
            'mrr_cents' => $state->mrrCents,
            'status' => $state->status->value,
            'cancellation_type' => $state->cancellationType?->value,
            'cancellation_reason' => $state->cancellationReason,
        ];
    }

    /**
     * @return array{accounts: int, mrr_cents: int}
     */
    private function bucket(): array
    {
        return ['accounts' => 0, 'mrr_cents' => 0];
    }

    /**
     * @param  array{accounts: int, mrr_cents: int}  $bucket
     */
    private function add(array &$bucket, int $accounts, int $mrr): void
    {
        $bucket['accounts'] += $accounts;
        $bucket['mrr_cents'] += $mrr;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function zeroes(array $keys): array
    {
        return array_fill_keys($keys, 0);
    }
}
