<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\FakeDiskSpace;

beforeEach(fn () => config([
    'rxdesk.storage.default_quota' => '5GB',
    'rxdesk.storage.disk_guard_threshold' => '10GB',
]));

/**
 * An Owner with one workspace whose uploads take `$bytes`.
 */
function usageOwner(string $name, string $email, int $bytes): User
{
    $owner = User::factory()->create(['name' => $name, 'email' => $email]);
    $owner->account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create(['account_id' => $owner->account_id, 'user_id' => $owner->id]);
    Media::factory()->temporaryUpload($workspace)->create(['size' => $bytes]);

    return $owner;
}

test("usage lists each Owner's Storage used and Quota", function () {
    usageOwner('Ann', 'ann@example.com', 1536 * 1024 ** 2);
    $bob = usageOwner('Bob', 'bob@example.com', 300 * 1024 ** 2);
    $bob->account->forceFill(['quota_bytes' => 20 * 1024 ** 3])->save();
    User::factory()->create(['name' => 'Cleo', 'email' => 'cleo@example.com']);

    expect(Artisan::call('rxdesk:usage'))->toBe(0);

    $output = Artisan::output();

    expect($output)
        ->toContain('Ann <ann@example.com>')
        ->toContain('1.5 GB')
        ->toContain('5 GB (default)')
        ->toContain('Bob <bob@example.com>')
        ->toContain('300 MB')
        ->toContain('20 GB')
        ->not->toContain('cleo@example.com')
        ->toContain('Total: 1.8 GB used of 25 GB handed out.');
});

test('usage says when there are no Owners yet', function () {
    expect(Artisan::call('rxdesk:usage'))->toBe(0)
        ->and(Artisan::output())->toContain('No Owners yet.');
});

test('usage shows the disk total, free space, the threshold and an inactive Disk guard', function () {
    FakeDiskSpace::bind(free: 40 * 1024 ** 3, total: 100 * 1024 ** 3);
    usageOwner('Ann', 'ann@example.com', 1024);

    Artisan::call('rxdesk:usage');

    expect(Artisan::output())
        ->toContain('Disk total: 100 GB')
        ->toContain('Disk free: 40 GB')
        ->toContain('Disk guard threshold: 10 GB')
        ->toContain('Disk guard: inactive');
});

test('usage says the Disk guard is active once free space drops under the threshold', function () {
    FakeDiskSpace::bind(free: 9 * 1024 ** 3, total: 100 * 1024 ** 3);

    Artisan::call('rxdesk:usage');

    expect(Artisan::output())
        ->toContain('Disk free: 9 GB')
        ->toContain('Disk guard: ACTIVE: uploads and imports are refused');
});
