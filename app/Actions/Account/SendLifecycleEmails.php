<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\Account\LifecycleEmailType;
use App\Mail\Lifecycle\AccessStopped;
use App\Mail\Lifecycle\DeletionIn1Day;
use App\Mail\Lifecycle\DeletionIn7Days;
use App\Mail\Lifecycle\MemberDeletionIn7Days;
use App\Mail\Lifecycle\TrialEnding;
use App\Models\Account;
use App\Models\User;
use Closure;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the lifecycle emails that are due today, each once per recipient.
 *
 * Never-paid signups get no email: they are removed 30 days after signup
 * without one. A Suspended Account gets none either: its clock is frozen.
 */
class SendLifecycleEmails
{
    public static function execute(Account $account): void
    {
        if (config('trypost.self_hosted') || $account->isSuspended()) {
            return;
        }

        $owner = $account->owner;

        if ($owner === null) {
            return;
        }

        self::trialEnding($account, $owner);

        if ($account->isNeverPaid()) {
            return;
        }

        self::accessStopped($account, $owner);
        self::deletionWarnings($account, $owner);
    }

    private static function trialEnding(Account $account, User $owner): void
    {
        $chargeAt = $account->activeTrialEndsAt();

        if ($chargeAt === null || $chargeAt->isPast() || $chargeAt->gt(now()->addDays(3))) {
            return;
        }

        self::sendOnce(
            $account,
            $owner,
            LifecycleEmailType::TrialEnding,
            fn (): Mailable => new TrialEnding($account),
        );
    }

    private static function accessStopped(Account $account, User $owner): void
    {
        if ($account->hasAppAccess() || $account->hasPendingFirstCharge()) {
            return;
        }

        self::sendOnce(
            $account,
            $owner,
            LifecycleEmailType::AccessStopped,
            fn (): Mailable => new AccessStopped($account),
        );
    }

    private static function deletionWarnings(Account $account, User $owner): void
    {
        $daysLeft = $account->deletionDaysLeft();

        if ($daysLeft === null || $account->deletionIsDue()) {
            return;
        }

        if ($daysLeft <= 1) {
            self::sendOnce(
                $account,
                $owner,
                LifecycleEmailType::DeletionIn1Day,
                fn (): Mailable => new DeletionIn1Day($account),
            );

            return;
        }

        if ($daysLeft > 7) {
            return;
        }

        self::sendOnce(
            $account,
            $owner,
            LifecycleEmailType::DeletionIn7Days,
            fn (): Mailable => new DeletionIn7Days($account),
        );

        $account->users()
            ->where('id', '!=', $account->owner_id)
            ->get()
            ->each(fn (User $member) => self::sendOnce(
                $account,
                $member,
                LifecycleEmailType::MemberDeletionIn7Days,
                fn (): Mailable => new MemberDeletionIn7Days($account, $member),
            ));
    }

    /**
     * @param  Closure(): Mailable  $mailable
     */
    private static function sendOnce(Account $account, User $recipient, LifecycleEmailType $type, Closure $mailable): void
    {
        if (! RecordLifecycleEmail::execute($account, $recipient, $type)) {
            return;
        }

        Mail::to($recipient)->send($mailable());
    }
}
