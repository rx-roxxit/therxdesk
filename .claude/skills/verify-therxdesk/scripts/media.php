<?php

declare(strict_types=1);

/*
 * The media-on-R2 proof fixture on the DEV database: an Instagram channel the
 * Insights page includes in analytics, its avatar stored on the default disk,
 * and two follower readings so the share card has something to draw. Never run
 * it against production. Run it through media.sh, which runs it in the app
 * image with the selling and r2 overlays when those are up.
 *
 *   php media.php seed    upload the avatar and write the readings (idempotent)
 *   php media.php clear   remove the channel, its readings and the avatar
 */

use App\Enums\Analytics\MetricPrecision;
use App\Enums\Analytics\ObservationProvenance;
use App\Enums\SocialAccount\Platform;
use App\Models\AnalyticsAccountDailySnapshot;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

$root = '/var/www/html';
require "{$root}/vendor/autoload.php";
$app = require "{$root}/bootstrap/app.php";
$app->make(Kernel::class)->bootstrap();

const OWNER_EMAIL = 'verify-agent@example.com';
const PROOF_USERNAME = 'verify-r2-proof';
const AVATAR_SOURCE = 'images/accounts/linkedin.png';
const AVATAR_PREFIX = 'avatars/verify-';

function proofWorkspace(): ?Workspace
{
    $owner = User::query()->where('email', OWNER_EMAIL)->first();

    if (blank($owner)) {
        return null;
    }

    return Workspace::query()->find($owner->current_workspace_id);
}

function proofChannel(Workspace $workspace): SocialAccount
{
    $channel = SocialAccount::query()
        ->where('workspace_id', $workspace->id)
        ->where('username', PROOF_USERNAME)
        ->first();

    if (filled($channel)) {
        return $channel;
    }

    // Without events: a created channel queues analytics reads. Instagram is
    // included in analytics; the fixture's own LinkedIn channel is not.
    return SocialAccount::withoutEvents(fn (): SocialAccount => SocialAccount::factory()->instagram()->create([
        'workspace_id' => $workspace->id,
        'position' => 1,
        'display_name' => 'Verify R2',
        'username' => PROOF_USERNAME,
        'timezone' => 'UTC',
    ]));
}

function seed(): void
{
    $workspace = proofWorkspace();

    if (blank($workspace)) {
        fwrite(STDERR, "no fixture: run fixture.sh up first\n");
        exit(1);
    }

    $disk = (string) config('filesystems.default');

    if (in_array($disk, ['local', 'public'], true)) {
        fwrite(STDERR, "the default disk is {$disk}; run media.sh up first\n");
        exit(1);
    }

    $channel = proofChannel($workspace);
    $bytes = file_get_contents(public_path(AVATAR_SOURCE));

    if ($bytes === false) {
        fwrite(STDERR, 'could not read '.public_path(AVATAR_SOURCE)."\n");
        exit(1);
    }

    $path = AVATAR_PREFIX.bin2hex(random_bytes(8)).'.png';
    Storage::put($path, $bytes, 'public');

    $channel->forceFill(['avatar_url' => $path])->save();

    $url = Storage::url($path);
    $now = now('UTC');

    foreach ([5, 3] as $daysAgo) {
        AnalyticsAccountDailySnapshot::query()->updateOrCreate(
            [
                'social_account_id' => $channel->id,
                'date' => $now->copy()->subDays($daysAgo)->toDateString(),
            ],
            [
                'workspace_id' => $workspace->id,
                'social_account_key' => $channel->id,
                'network' => Platform::Instagram->network(),
                'platform' => Platform::Instagram,
                'platform_user_id' => $channel->platform_user_id,
                'account_display_name' => $channel->display_name,
                'account_username' => $channel->username,
                'account_avatar_url' => $url,
                'followers_count' => 122 + $daysAgo,
                'metrics' => null,
                'provenance' => ObservationProvenance::Actual,
                'precision' => MetricPrecision::Exact,
                'provider_observed_at' => $now,
                'collected_at' => $now,
            ],
        );
    }

    echo json_encode([
        'disk' => $disk,
        'channelId' => $channel->id,
        'avatarPath' => $path,
        'avatarUrl' => $url,
        'snapshots' => 2,
    ], JSON_PRETTY_PRINT), PHP_EOL;
}

function clear(): void
{
    $workspace = proofWorkspace();

    if (blank($workspace)) {
        echo "no fixture\n";

        return;
    }

    $channel = SocialAccount::query()
        ->where('workspace_id', $workspace->id)
        ->where('username', PROOF_USERNAME)
        ->first();

    if (blank($channel)) {
        echo "no media proof channel\n";

        return;
    }

    $path = (string) $channel->getRawOriginal('avatar_url');

    if (str_starts_with($path, AVATAR_PREFIX)) {
        Storage::delete($path);
    }

    AnalyticsAccountDailySnapshot::query()->where('social_account_id', $channel->id)->delete();
    $channel->deleteQuietly();

    echo "media proof removed\n";
}

$command = $argv[1] ?? '';

if ($command === 'seed') {
    seed();
    exit(0);
}

if ($command === 'clear') {
    clear();
    exit(0);
}

fwrite(STDERR, "usage: php media.php seed|clear\n");
exit(2);
