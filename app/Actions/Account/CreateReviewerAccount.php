<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Actions\Audit\RecordAuditEntry;
use App\Actions\User\CreateUser;
use App\Models\Account;
use App\Models\Plan;
use App\Services\Billing\ComplimentaryPlans;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * A platform reviewer's test Account, created by the System admin through
 * /internal/v1: an Owner with a verified email, the default Workspace, a
 * Complimentary Plan and no email sent. The Account may be given Review
 * access at once, and the whole creation is audited without its password.
 */
class CreateReviewerAccount
{
    public function __construct(private readonly ComplimentaryPlans $complimentary) {}

    public function execute(
        string $email,
        string $password,
        Plan $plan,
        ?CarbonInterface $complimentaryUntil,
        bool $reviewAccess,
        string $actor,
    ): Account {
        $user = CreateUser::execute([
            'name' => Str::before($email, '@'),
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
            'is_invite' => false,
            'registration_ip' => null,
            'country' => null,
            'locale' => null,
            'timezone' => null,
            'week_starts_on' => null,
            'time_format' => null,
        ]);

        $account = $this->complimentary->give($user->account, $plan, $complimentaryUntil, $actor);

        if ($reviewAccess) {
            $account->forceFill(['review_access' => true])->save();
        }

        RecordAuditEntry::execute($actor, 'account.create', $account, [], [
            'account_id' => $account->id,
            'owner_id' => $user->id,
            'email' => $user->email,
            'plan_id' => $plan->id,
            'complimentary_until' => $account->complimentary_until?->toIso8601String(),
            'review_access' => (bool) $account->review_access,
        ]);

        return $account->fresh(['owner', 'plan']);
    }
}
