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
 */

use App\Actions\Workspace\PurgeWorkspace;
use App\Models\Account;
use App\Models\SocialAccount;
use App\Models\TrialAccount;
use App\Models\TrialCard;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Cashier\Cashier;

$root = '/var/www/html';
require "{$root}/vendor/autoload.php";
$app = require "{$root}/bootstrap/app.php";
$app->make(Kernel::class)->bootstrap();

const OWNER_EMAIL = 'verify-agent@example.com';
const OWNER_PASSWORD = 'password';

$guestEmailLike = (string) getenv('VERIFY_GUEST_EMAIL_LIKE');

/**
 * @param  Collection<int, User>  $owners
 */
function purge(Collection $owners): void
{
    Workspace::query()
        ->whereIn('account_id', $owners->pluck('account_id'))
        ->get()
        ->each(fn (Workspace $workspace) => PurgeWorkspace::execute($workspace));

    $accountIds = $owners->pluck('account_id')->unique();

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

    User::query()->whereIn('id', $owners->pluck('id'))->delete();
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

    return [
        'email' => OWNER_EMAIL,
        'password' => OWNER_PASSWORD,
        'userId' => $owner->id,
        'accountId' => $owner->account_id,
        'stripeId' => $owner->account?->stripe_id,
        'workspaceId' => $workspace?->id,
        'channelId' => $channel?->id,
        'channelPlatform' => $channel?->platform->value,
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

if ($command === 'guests') {
    if (blank($guestEmailLike)) {
        fwrite(STDERR, "VERIFY_GUEST_EMAIL_LIKE is empty: run fixture.sh, not php directly\n");
        exit(2);
    }

    forgetGuests($guestEmailLike);
    echo "guest signups removed\n";
    exit(0);
}

fwrite(STDERR, "usage: fixture.php up|show|down|guests|customer <cus_id>|customer-clear|card <fingerprint>\n");
exit(2);
