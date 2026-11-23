<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use App\Models\Account;
use App\Models\User;
use App\Support\Mail\RecipientTime;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Shared data for the five lifecycle emails. Copy lives in the Maizzle
 * template under `rxdesk.lifecycle`, resolved in the recipient's locale by
 * `Mail::to($user)`.
 */
abstract class LifecycleMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Account $account) {}

    protected function supportUrl(): string
    {
        return 'mailto:support@therxdesk.com';
    }

    protected function localDate(?CarbonInterface $at, User $recipient): ?string
    {
        if ($at === null) {
            return null;
        }

        return $at->copy()
            ->setTimezone(RecipientTime::timezone($recipient))
            ->locale(app()->getLocale())
            ->isoFormat('LL');
    }
}
