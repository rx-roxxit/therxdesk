<?php

declare(strict_types=1);

use App\Enums\Account\VideoPolicy;
use App\Enums\Availability\ServiceSwitch;
use App\Enums\Media\Source;
use App\Models\MediaSourceConnection;
use App\Models\User;
use App\Support\Availability\Availability;
use Inertia\Testing\AssertableInertia;

function memberOfAccountOf(User $owner): User
{
    return User::factory()->create(['account_id' => $owner->account_id]);
}

function connectDriveForBackup(User $owner): void
{
    config([
        'services.google_media.client_id' => 'media-client-id',
        'services.google_media.client_secret' => 'media-client-secret',
        'services.google_media.backup_redirect' => 'https://app.test/settings/preferences/drive/callback',
    ]);

    MediaSourceConnection::factory()->create([
        'user_id' => $owner->id,
        'source' => Source::GoogleDriveBackup,
        'access_token' => 'drive-access',
    ]);
}

test('the owner sees the video policy on the preferences page, keep by default', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('app.settings.preferences'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/profile/Preferences')
            ->where('videoPolicy', 'keep'));
});

test('a member does not see the video policy', function () {
    $member = memberOfAccountOf(User::factory()->create());

    $this->actingAs($member)
        ->get(route('app.settings.preferences'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/profile/Preferences')
            ->where('videoPolicy', null));
});

test('the owner changes the video policy on its own', function (string $from, string $to) {
    $owner = User::factory()->create();
    $owner->account->forceFill(['video_policy' => $from])->save();

    $this->actingAs($owner)
        ->from(route('app.settings.preferences'))
        ->patch(route('app.settings.video-policy.update'), ['video_policy' => $to])
        ->assertRedirect(route('app.settings.preferences'))
        ->assertSessionHasNoErrors();

    expect($owner->account->refresh()->video_policy)->toBe(VideoPolicy::from($to));
})->with([
    'keep to delete' => ['keep', 'delete'],
    'delete to keep' => ['delete', 'keep'],
]);

test('a JSON save of the video policy answers with no content', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'delete'])
        ->assertNoContent();

    expect($owner->account->refresh()->video_policy)->toBe(VideoPolicy::Delete);
});

test('a member cannot change the video policy', function () {
    $owner = User::factory()->create();
    $member = memberOfAccountOf($owner);

    $this->actingAs($member)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'delete'])
        ->assertForbidden();

    expect($owner->account->refresh()->video_policy)->toBe(VideoPolicy::Keep);
});

test('backup cannot be chosen yet', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'backup'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('video_policy');

    expect($owner->account->refresh()->video_policy)->toBe(VideoPolicy::Keep);
});

test('backup can be chosen with a Drive connection while its switch is on', function () {
    $owner = User::factory()->create();
    connectDriveForBackup($owner);

    $this->actingAs($owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'backup'])
        ->assertNoContent();

    expect($owner->account->refresh()->video_policy)->toBe(VideoPolicy::Backup);
});

test('backup is refused with a translated message while Drive backup is switched off', function () {
    $owner = User::factory()->create();
    connectDriveForBackup($owner);
    app(Availability::class)->set(ServiceSwitch::DriveBackup->value, false);

    $this->actingAs($owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'backup'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'video_policy' => __('rxdesk.video_policy.errors.backup_unavailable'),
        ]);

    expect($owner->account->refresh()->video_policy)->toBe(VideoPolicy::Keep);
});

test('an owner already on backup keeps it while Drive backup is switched off', function () {
    $owner = User::factory()->create();
    $owner->account->forceFill(['video_policy' => VideoPolicy::Backup])->save();
    connectDriveForBackup($owner);
    app(Availability::class)->set(ServiceSwitch::DriveBackup->value, false);

    $this->actingAs($owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'backup'])
        ->assertNoContent();

    expect($owner->account->refresh()->video_policy)->toBe(VideoPolicy::Backup);
});

test('the preferences page tells the Owner whether Drive backup is offered', function () {
    $owner = User::factory()->create();
    connectDriveForBackup($owner);
    $availability = app(Availability::class);

    $this->actingAs($owner)
        ->get(route('app.settings.preferences'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('driveBackupEnabled', true));

    $availability->set(ServiceSwitch::DriveBackup->value, false);

    $this->actingAs($owner)
        ->get(route('app.settings.preferences'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('driveBackupEnabled', false));
});

test('an unknown video policy is refused', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'archive'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('video_policy');
});

test('guests cannot change the video policy', function () {
    $this->patch(route('app.settings.video-policy.update'), ['video_policy' => 'delete'])
        ->assertRedirect(route('login'));
});
