<?php

declare(strict_types=1);

namespace App\Enums\Account;

/**
 * The lifecycle emails The RX Desk sends. Each one is recorded in
 * `lifecycle_emails` so it is sent once per recipient.
 */
enum LifecycleEmailType: string
{
    case TrialEnding = 'trial_ending';
    case AccessStopped = 'access_stopped';
    case DeletionIn7Days = 'deletion_in_7_days';
    case DeletionIn1Day = 'deletion_in_1_day';
    case MemberDeletionIn7Days = 'member_deletion_in_7_days';

    /**
     * The lang key block under `rxdesk.lifecycle`.
     */
    public function langKey(): string
    {
        return "rxdesk.lifecycle.{$this->value}";
    }
}
