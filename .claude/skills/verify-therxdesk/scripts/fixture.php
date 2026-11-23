<?php

declare(strict_types=1);

/*
 * The verification fixture on the DEV database: one Owner with one Workspace
 * and one made-up LinkedIn channel, nothing else. Never run it against
 * production. Run it through fixture.sh, which runs it in the app image.
 *
 *   php fixture.php up     replace any previous fixture with a fresh one, print it as JSON
 *   php fixture.php show   print the current fixture as JSON (read-only), or exit 1
 *   php fixture.php down   remove the fixture and everything it owns
 *   php fixture.php guests remove the Owners the register journey signed up
 *   php fixture.php customer <cus_id>      point the fixture Account at a sandbox Customer
 *   php fixture.php customer-clear         forget that Customer
 *   php fixture.php card <fingerprint>     claim a card fingerprint for the fixture Account
 *   php fixture.php token                  issue a personal API token and print ONLY it (api.sh captures it)
 */

use App\Actions\ApiKey\IssueApiKey;
use App\Actions\Workspace\PurgeWorkspace;
use App\Models\AccessToken;
use App\Enums\User\Goal;
use App\Enums\User\Persona;
use App\Enums\User\PreviousSoftware;
use App\Models\Account;
use App\Models\SocialAccount;
use App\Models\TrialAccount;
use App\Models\TrialCard;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Laravel\Cashier\Cashier;

$root = '/var/www/html';
require "{$root}/vendor/autoload.php";
$app = require "{$root}/bootstrap/app.php";
$app->make(Kernel::class)->bootstrap();

const OWNER_EMAIL = 'verify-agent@example.com';
const OWNER_PASSWORD = 'password';

// A Member of the fixture Workspace, on the same Account: the Suspended screen
// and every access rule must treat them like the Owner.
const MEMBER_EMAIL = 'verify-member@example.com';
const MEMBER_PASSWORD = 'password';

// The name api.sh's token is issued under, so stale ones are revoked first.
const API_TOKEN_NAME = 'verify-agent-api';

$guestEmailLike = (string) getenv('VERIFY_GUEST_EMAIL_LIKE');

/**
 * @param  Collection<int, User>  $owners
 */
function purge(Collection $owners): void
{
    $accountIds = $owners->pluck('account_id')->filter()->unique();

    // The fixture Workspace also has Members on the same Account; they own
    // nothing of their own, so they go with the fixture.
    $users = User::query()
        ->whereIn('id', $owners->pluck('id'))
        ->orWhereIn('account_id', $accountIds)
        ->get();

    Workspace::query()
        ->whereIn('account_id', $accountIds)
        ->get()
        ->each(fn (Workspace $workspace) => PurgeWorkspace::execute($workspace));

    // The cards and accounts that started a Trial are its record, and both
    // tables have no foreign key on purpose. Delete them here or the 4242 card
    // could never start a Trial again after fixture.sh up.
    TrialCard::query()->whereIn('account_id', $accountIds)->delete();
    TrialAccount::query()->whereIn('account_id', $accountIds)->delete();

    // A selling-mode run leaves a Customer (and its subscription) in the
    // Stripe sandbox; deleting the Customer cancels its subscriptions.
    Account::query()
        ->whereIn('id', $accountIds)
        ->whereNotNull('stripe_id')
        ->pluck('stripe_id')
        ->each(fn (string $customerId) => rescue(
            fn () => Cashier::stripe()->customers->delete($customerId),
            fn () => fwrite(STDERR, "could not delete the sandbox customer {$customerId}\n"),
            report: false,
        ));

    User::query()->whereIn('id', $users->pluck('id'))->delete();
    Account::query()->whereIn('id', $accountIds)->delete();
}

function forget(): void
{
    purge(User::query()->where('email', OWNER_EMAIL)->get());
}

function forgetGuests(string $emailLike): void
{
    purge(User::query()->whereLike('email', $emailLike)->get());
}

function describe(): ?array
{
    $owner = User::query()->where('email', OWNER_EMAIL)->first();

    if (blank($owner)) {
        return null;
    }

    $workspace = Workspace::query()->find($owner->current_workspace_id);
    $channel = $workspace?->socialAccounts()->first();
    $member = User::query()
        ->where('email', MEMBER_EMAIL)
        ->where('account_id', $owner->account_id)
        ->first();

    return [
        'email' => OWNER_EMAIL,
        'password' => OWNER_PASSWORD,
        'userId' => $owner->id,
        'accountId' => $owner->account_id,
        'stripeId' => $owner->account?->stripe_id,
        'workspaceId' => $workspace?->id,
        'channelId' => $channel?->id,
        'channelPlatform' => $channel?->platform->value,
        'memberEmail' => MEMBER_EMAIL,
        'memberId' => $member?->id,
        'deletionScheduledAt' => $owner->account?->deletion_scheduled_at?->toIso8601String(),
    ];
}

function fixtureAccount(): ?Account
{
    return User::query()->where('email', OWNER_EMAIL)->first()?->account;
}

function seed(): void
{
    forget();

    $owner = User::factory()->create([
        'name' => 'Verify Agent',
        'email' => OWNER_EMAIL,
        'timezone' => 'UTC',
        'country' => 'TH',
    ]);

    $workspace = Workspace::factory()->create([
        'name' => 'Verify Desk',
        'user_id' => $owner->id,
        'account_id' => $owner->account_id,
    ]);
    $workspace->members()->attach($owner->id, ['is_admin' => true, 'requires_approval' => false]);
    $owner->update(['current_workspace_id' => $workspace->id]);

    // Without events: a created channel queues analytics reads, which would
    // call LinkedIn with the factory's made-up token.
    SocialAccount::withoutEvents(fn (): SocialAccount => SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $workspace->id,
        'position' => 0,
        'display_name' => 'Verify Desk',
        'username' => 'verifydesk',
        'timezone' => 'UTC',
    ]));

    // A Member on the same Account: a Suspension, a Lapse and the app's
    // access rules must treat them exactly like the Owner.
    $member = User::factory()->create([
        'name' => 'Verify Member',
        'email' => MEMBER_EMAIL,
        'password' => Hash::make(MEMBER_PASSWORD),
        'timezone' => 'UTC',
        'country' => 'TH',
        'account_id' => $owner->account_id,
    ]);
    $workspace->members()->attach($member->id, ['is_admin' => false, 'requires_approval' => false]);
    $member->update(['current_workspace_id' => $workspace->id]);
}

$command = $argv[1] ?? '';

if ($command === 'up') {
    seed();
    echo json_encode(describe(), JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

if ($command === 'show') {
    $fixture = describe();

    if (blank($fixture)) {
        fwrite(STDERR, "no fixture: run fixture.sh up\n");
        exit(1);
    }

    echo json_encode($fixture, JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

if ($command === 'down') {
    forget();
    echo "fixture removed\n";
    exit(0);
}

// Stripe's test clock creates the Customer itself; the Account must point at
// it or Checkout makes a second one. stripe-clock.sh attach calls this.
if ($command === 'customer') {
    $customerId = $argv[2] ?? '';

    if (blank($customerId)) {
        fwrite(STDERR, "usage: fixture.php customer <cus_id>\n");
        exit(2);
    }

    $account = fixtureAccount();

    if ($account === null) {
        fwrite(STDERR, "no fixture: run fixture.sh up\n");
        exit(1);
    }

    $account->forceFill(['stripe_id' => $customerId])->save();

    echo json_encode(describe(), JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

if ($command === 'customer-clear') {
    $account = fixtureAccount();

    if ($account === null) {
        fwrite(STDERR, "no fixture: run fixture.sh up\n");
        exit(1);
    }

    $account->forceFill(['stripe_id' => null])->save();

    echo json_encode(describe(), JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

// Claim a card fingerprint for the fixture Account, as if it had started a
// Trial before: the next Checkout on that card is charged at once.
if ($command === 'card') {
    $fingerprint = $argv[2] ?? '';

    if (blank($fingerprint)) {
        fwrite(STDERR, "usage: fixture.php card <fingerprint>\n");
        exit(2);
    }

    $account = fixtureAccount();

    if ($account === null) {
        fwrite(STDERR, "no fixture: run fixture.sh up\n");
        exit(1);
    }

    TrialCard::query()->updateOrCreate(
        ['fingerprint' => $fingerprint],
        ['account_id' => $account->id, 'stripe_subscription_id' => null],
    );

    echo "card {$fingerprint} claimed for the fixture Account\n";
    exit(0);
}

// Move the fixture Account onto the Lapsed path with a deletion clock <hours>
// from now (negative = already due), so the lifecycle sweep can send emails and
// the plan step can show the date. Also completes onboarding so /welcome/plan
// renders instead of the persona step.
if ($command === 'lifecycle') {
    $hours = (int) ($argv[2] ?? 168);
    $account = fixtureAccount();

    if ($account === null || $account->owner === null) {
        fwrite(STDERR, "no fixture: run fixture.sh up\n");
        exit(1);
    }

    $account->owner->forceFill([
        'persona' => Persona::Agency->value,
        'goals' => [Goal::SaveTime->value],
        'previous_software' => PreviousSoftware::Buffer->value,
    ])->save();

    $account->forceFill([
        'first_paid_at' => now()->subMonth(),
        'deletion_scheduled_at' => now()->addHours($hours),
        'suspended_at' => null,
        'suspension_reason' => null,
    ])->save();

    if (! $account->subscriptions()->exists()) {
        $account->subscriptions()->create([
            'type' => Account::SUBSCRIPTION_NAME,
            'stripe_id' => 'sub_verify_lapsed',
            'stripe_status' => 'active',
            'stripe_price' => 'price_verify_lapsed',
            'ends_at' => now()->subDay(),
        ]);
    }

    echo json_encode([
        'accountId' => $account->id,
        'deletionScheduledAt' => $account->fresh()->deletion_scheduled_at?->toIso8601String(),
        'ownerEmail' => $account->owner->email,
        'memberEmail' => MEMBER_EMAIL,
    ], JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

if ($command === 'guests') {
    if (blank($guestEmailLike)) {
        fwrite(STDERR, "VERIFY_GUEST_EMAIL_LIKE is empty: run fixture.sh, not php directly\n");
        exit(2);
    }

    forgetGuests($guestEmailLike);
    echo "guest signups removed\n";
    exit(0);
}

// The public API as the fixture Owner. api.sh captures this line into a
// mode-600 curl config; it must stay the only output.
if ($command === 'token') {
    $owner = User::query()->where('email', OWNER_EMAIL)->first();
    $workspace = $owner === null
        ? null
        : Workspace::query()->find($owner->current_workspace_id);

    if ($owner === null || $workspace === null) {
        fwrite(STDERR, "no fixture: run fixture.sh up\n");
        exit(1);
    }

    // A personal token's plain value is only known at issue time, so revoke an
    // earlier one rather than leave live keys behind in the dev database.
    AccessToken::query()
        ->where('user_id', $owner->id)
        ->where('workspace_id', $workspace->id)
        ->where('name', API_TOKEN_NAME)
        ->update(['revoked' => true]);

    $issued = IssueApiKey::execute($owner, $workspace, ['name' => API_TOKEN_NAME]);

    echo $issued['plain_token'], PHP_EOL;
    exit(0);
}

fwrite(STDERR, "usage: fixture.php up|show|down|guests|customer <cus_id>|customer-clear|card <fingerprint>|token\n");
exit(2);
