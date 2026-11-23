<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use App\Models\BusinessSnapshot;
use App\Support\Business\BusinessStateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * The daily Business snapshot: one row per Account that has a Subscription, a
 * Trial, a Complimentary Plan or a Suspension, holding its status and MRR for
 * the day. Idempotent per day, kept indefinitely, and never runs while the app
 * is self-hosted.
 */
class BusinessSnapshotCommand extends Command
{
    protected $signature = 'rxdesk:business-snapshot
        {--date= : The UTC day to write (YYYY-MM-DD), today by default}';

    protected $description = 'Write one business metrics snapshot row per Account for the day';

    public function handle(BusinessStateResolver $resolver): int
    {
        $at = $this->snapshotAt();

        if ($at === null) {
            $this->error('The date must be a UTC day in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        if (config('trypost.self_hosted')) {
            $this->info('Self-hosted: business snapshots are off.');

            return self::SUCCESS;
        }

        $written = $this->write($resolver, $at);

        $this->info("Business snapshot for {$at->toDateString()}: {$written} Accounts.");

        return self::SUCCESS;
    }

    private function write(BusinessStateResolver $resolver, CarbonImmutable $at): int
    {
        $written = 0;
        $stamp = now('UTC');

        $resolver->accounts()->chunkById(200, function (Collection $accounts) use ($resolver, $at, $stamp, &$written): void {
            $rows = [];

            foreach ($accounts as $account) {
                $state = $resolver->stateFor($account, $at);

                $rows[] = [
                    'date' => $at->toDateString(),
                    'account_id' => $state->accountId,
                    'plan_id' => $state->planId,
                    'interval' => $state->interval?->value,
                    'mrr_cents' => $state->mrrCents,
                    'status' => $state->status->value,
                    'offer_id' => $state->offerId,
                    'cancellation_type' => $state->cancellationType?->value,
                    'cancellation_reason' => $state->cancellationReason,
                    'created_at' => $stamp,
                    'updated_at' => $stamp,
                ];
            }

            if ($rows === []) {
                return;
            }

            BusinessSnapshot::query()->upsert(
                $rows,
                ['date', 'account_id'],
                ['plan_id', 'interval', 'mrr_cents', 'status', 'offer_id', 'cancellation_type', 'cancellation_reason', 'updated_at'],
            );

            $written += count($rows);
        });

        return $written;
    }

    private function snapshotAt(): ?CarbonImmutable
    {
        $date = $this->option('date');

        if (blank($date)) {
            return CarbonImmutable::now('UTC');
        }

        $date = (string) $date;

        if (! CarbonImmutable::canBeCreatedFromFormat($date, 'Y-m-d')) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $date, 'UTC')->startOfDay()->addMinutes(5);
    }
}
