<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\FakeDiskSpace;

beforeEach(function () {
    config([
        'rxdesk.storage.default_quota' => '5GB',
        'rxdesk.storage.disk_guard_threshold' => '10GB',
    ]);
});

/**
 * A user who owns an account with one workspace.
 */
function quotaOwner(string $email, string $name = 'Ann'): User
{
    $owner = User::factory()->create(['email' => $email, 'name' => $name]);
    $owner->account->update(['owner_id' => $owner->id]);
    Workspace::factory()->create(['account_id' => $owner->account_id, 'user_id' => $owner->id]);

    return $owner;
}

test('set gives an Owner their own Quota from a human-readable size', function () {
    $owner = quotaOwner('ann@example.com');

    expect(Artisan::call('rxdesk:quota', ['action' => 'set', 'email' => 'ann@example.com', 'size' => '20GB']))->toBe(0)
        ->and(Artisan::output())->toContain('ann@example.com now has a Quota of 20 GB.')
        ->and($owner->account->fresh()->quota_bytes)->toBe(20 * 1024 ** 3);
});

test('set accepts sizes written in several ways', function (string $size, int $bytes) {
    $owner = quotaOwner('ann@example.com');

    Artisan::call('rxdesk:quota', ['action' => 'set', 'email' => 'ann@example.com', 'size' => $size]);

    expect($owner->account->fresh()->quota_bytes)->toBe($bytes);
})->with([
    'spaced lowercase' => ['500 mb', 500 * 1024 ** 2],
    'decimal' => ['1.5GB', (int) (1.5 * 1024 ** 3)],
    'short unit' => ['2T', 2 * 1024 ** 4],
]);

test('set default puts an Owner back on the default Quota', function () {
    $owner = quotaOwner('ann@example.com');
    $owner->account->forceFill(['quota_bytes' => 1024])->save();

    expect(Artisan::call('rxdesk:quota', ['action' => 'set', 'email' => 'ann@example.com', 'size' => 'default']))->toBe(0)
        ->and(Artisan::output())->toContain('ann@example.com is back on the default Quota of 5 GB.')
        ->and($owner->account->fresh()->quota_bytes)->toBeNull();
});

test('set refuses a size it cannot read', function () {
    $owner = quotaOwner('ann@example.com');

    expect(Artisan::call('rxdesk:quota', ['action' => 'set', 'email' => 'ann@example.com', 'size' => 'lots']))->toBe(1)
        ->and(Artisan::output())->toContain('"lots" is not a size')
        ->and($owner->account->fresh()->quota_bytes)->toBeNull();
});

test('show prints Storage used and the Quota, and whether it is the default', function () {
    $owner = quotaOwner('ann@example.com');
    Media::factory()->temporaryUpload($owner->account->workspaces()->first())->create(['size' => 1536 * 1024 ** 2]);

    expect(Artisan::call('rxdesk:quota', ['action' => 'show', 'email' => 'ann@example.com']))->toBe(0)
        ->and(Artisan::output())->toContain('ann@example.com uses 1.5 GB of 5 GB (default Quota).');

    $owner->account->forceFill(['quota_bytes' => 20 * 1024 ** 3])->save();
    Artisan::call('rxdesk:quota', ['action' => 'show', 'email' => 'ann@example.com']);

    expect(Artisan::output())->toContain('ann@example.com uses 1.5 GB of 20 GB.');
});

test('an unknown email or a user who owns no workspaces has no Quota', function (Closure $makeUser, string $message) {
    $makeUser();

    expect(Artisan::call('rxdesk:quota', ['action' => 'show', 'email' => 'bob@example.com']))->toBe(1)
        ->and(Artisan::output())->toContain($message);
})->with([
    'unknown' => [fn () => null, 'No user with the email bob@example.com.'],
    'member' => [fn () => User::factory()->create(['email' => 'bob@example.com']), 'bob@example.com owns no workspaces, so they have no Quota.'],
]);

test('set warns when the Quotas add up to more than free disk plus Storage used, minus the Disk guard threshold', function () {
    FakeDiskSpace::bind(free: 20 * 1024 ** 3);
    $ann = quotaOwner('ann@example.com');
    quotaOwner('bob@example.com', 'Bob');
    Media::factory()->temporaryUpload($ann->account->workspaces()->first())->create(['size' => 2 * 1024 ** 3]);

    Artisan::call('rxdesk:quota', ['action' => 'set', 'email' => 'ann@example.com', 'size' => '7GB']);

    expect(Artisan::output())->toContain('The Quotas add up to 12 GB, within the 12 GB the disk can give to media.');

    Artisan::call('rxdesk:quota', ['action' => 'set', 'email' => 'ann@example.com', 'size' => '8GB']);

    expect(Artisan::output())->toContain('Warning: the Quotas add up to 13 GB, more than the 12 GB the disk can give to media');
});

test('the space media can use never goes below nothing', function () {
    FakeDiskSpace::bind(free: 4 * 1024 ** 3);
    quotaOwner('ann@example.com');

    Artisan::call('rxdesk:quota', ['action' => 'show', 'email' => 'ann@example.com']);

    expect(Artisan::output())->toContain('Warning: the Quotas add up to 5 GB, more than the 0 B the disk can give to media');
});

test('with media on object storage the command reports the total and no over-commit warning', function () {
    config(['filesystems.default' => 'r2', 'filesystems.disks.r2.driver' => 's3']);
    FakeDiskSpace::bind(free: 1);
    quotaOwner('ann@example.com');

    Artisan::call('rxdesk:quota', ['action' => 'show', 'email' => 'ann@example.com']);

    expect(Artisan::output())->toContain('The Quotas add up to 5 GB.')
        ->not->toContain('Warning')
        ->not->toContain('disk can give to media');
});

test('an unknown action fails', function () {
    expect(Artisan::call('rxdesk:quota', ['action' => 'drop', 'email' => 'ann@example.com']))->toBe(1)
        ->and(Artisan::output())->toContain('Unknown action "drop". Use show or set.');
});
