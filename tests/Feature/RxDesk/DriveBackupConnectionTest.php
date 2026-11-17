<?php

declare(strict_types=1);

use App\Actions\VideoPolicy\MarkDriveNeedsReconnect;
use App\Enums\Account\VideoPolicy;
use App\Enums\Media\Source;
use App\Models\MediaSourceConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Uri;
use Inertia\Testing\AssertableInertia;

const DRIVE_BACKUP_TEST_REDIRECT = 'https://desk.example/settings/preferences/drive/callback';

const DRIVE_BACKUP_SCOPE = 'https://www.googleapis.com/auth/drive.file';

beforeEach(function () {
    config()->set([
        'services.google_media.client_id' => 'media-client-id',
        'services.google_media.client_secret' => 'media-client-secret',
        'services.google_media.backup_redirect' => DRIVE_BACKUP_TEST_REDIRECT,
        'trypost.media_sources.google_oauth.authorize_url' => 'https://accounts.google.test/auth',
        'trypost.media_sources.google_oauth.token_api' => 'https://oauth.google.test',
    ]);

    $this->owner = User::factory()->create();
    $this->member = User::factory()->create(['account_id' => $this->owner->account_id]);
});

function driveConnectionFor(User $owner, array $state = []): MediaSourceConnection
{
    return MediaSourceConnection::factory()->create([
        'user_id' => $owner->id,
        'source' => Source::GoogleDriveBackup,
        ...$state,
    ]);
}

function startDriveConnect(object $test, User $user): array
{
    $response = $test->actingAs($user)->get(route('app.settings.drive-backup.connect'));
    $response->assertRedirect();

    return Uri::of($response->headers->get('Location'))->query()->all();
}

test('the owner is sent to Google with only drive.file, offline access and a consent prompt', function () {
    $response = $this->actingAs($this->owner)->get(route('app.settings.drive-backup.connect'));

    $location = Uri::of($response->headers->get('Location'));

    expect($location->scheme())->toBe('https')
        ->and($location->host())->toBe('accounts.google.test')
        ->and($location->query()->all())->toMatchArray([
            'client_id' => 'media-client-id',
            'redirect_uri' => DRIVE_BACKUP_TEST_REDIRECT,
            'response_type' => 'code',
            'scope' => DRIVE_BACKUP_SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'code_challenge_method' => 'S256',
        ])
        ->and($location->query()->all())->not->toHaveKey('include_granted_scopes');
});

test('a member cannot start, finish or remove the drive connection', function () {
    $this->actingAs($this->member)->get(route('app.settings.drive-backup.connect'))->assertForbidden();
    $this->actingAs($this->member)->get(route('app.settings.drive-backup.callback', ['code' => 'x', 'state' => 'y']))->assertForbidden();
    $this->actingAs($this->member)->delete(route('app.settings.drive-backup.destroy'))->assertForbidden();
});

test('the callback keeps the refresh token encrypted under the backup source', function () {
    Http::fake(['oauth.google.test/*' => Http::response([
        'access_token' => 'ya29.access',
        'refresh_token' => '1//refresh-secret',
        'expires_in' => 3600,
        'scope' => DRIVE_BACKUP_SCOPE,
    ])]);

    $query = startDriveConnect($this, $this->owner);

    $this->actingAs($this->owner)
        ->get(route('app.settings.drive-backup.callback', ['code' => 'auth-code', 'state' => $query['state']]))
        ->assertRedirect(route('app.settings.preferences'))
        ->assertSessionHas('flash.success');

    $connection = MediaSourceConnection::query()->where('user_id', $this->owner->id)->sole();

    expect($connection->source)->toBe(Source::GoogleDriveBackup)
        ->and($connection->refresh_token)->toBe('1//refresh-secret')
        ->and($connection->needsReconnect())->toBeFalse()
        ->and(DB::table('media_source_connections')->value('refresh_token'))->not->toContain('1//refresh-secret');

    Http::assertSent(fn ($request) => $request['grant_type'] === 'authorization_code'
        && $request['redirect_uri'] === DRIVE_BACKUP_TEST_REDIRECT
        && $request['code'] === 'auth-code');
});

test('the backup connection is separate from the composer picker one', function () {
    $picker = MediaSourceConnection::factory()->create([
        'user_id' => $this->owner->id,
        'source' => Source::Canva,
    ]);
    Http::fake(['oauth.google.test/*' => Http::response([
        'access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600, 'scope' => DRIVE_BACKUP_SCOPE,
    ])]);

    $query = startDriveConnect($this, $this->owner);
    $this->actingAs($this->owner)->get(route('app.settings.drive-backup.callback', ['code' => 'c', 'state' => $query['state']]));

    expect(MediaSourceConnection::query()->where('user_id', $this->owner->id)->count())->toBe(2)
        ->and($picker->refresh()->source)->toBe(Source::Canva);
});

test('reconnecting replaces the token and clears needs reconnect', function () {
    $connection = driveConnectionFor($this->owner, ['needs_reconnect_at' => now()]);
    Http::fake(['oauth.google.test/*' => Http::response([
        'access_token' => 'a', 'refresh_token' => 'new-refresh', 'expires_in' => 3600, 'scope' => DRIVE_BACKUP_SCOPE,
    ])]);

    $query = startDriveConnect($this, $this->owner);
    $this->actingAs($this->owner)->get(route('app.settings.drive-backup.callback', ['code' => 'c', 'state' => $query['state']]));

    $connection->refresh();

    expect(MediaSourceConnection::query()->count())->toBe(1)
        ->and($connection->refresh_token)->toBe('new-refresh')
        ->and($connection->needsReconnect())->toBeFalse();
});

test('a callback that Google did not finish stores nothing', function (array $response, array $params) {
    Http::fake(['oauth.google.test/*' => Http::response($response)]);

    $query = startDriveConnect($this, $this->owner);

    $this->actingAs($this->owner)
        ->get(route('app.settings.drive-backup.callback', [...$params, 'state' => $query['state']]))
        ->assertRedirect(route('app.settings.preferences'))
        ->assertSessionHas('flash.error');

    expect(MediaSourceConnection::query()->count())->toBe(0);
})->with([
    'no refresh token' => [['access_token' => 'a', 'expires_in' => 3600, 'scope' => DRIVE_BACKUP_SCOPE], ['code' => 'c']],
    'drive.file not granted' => [['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600, 'scope' => 'openid'], ['code' => 'c']],
    'access denied' => [[], ['error' => 'access_denied']],
]);

test('a callback with the wrong state stores nothing and calls no Google host', function () {
    Http::fake();
    startDriveConnect($this, $this->owner);

    $this->actingAs($this->owner)
        ->get(route('app.settings.drive-backup.callback', ['code' => 'c', 'state' => 'forged']))
        ->assertRedirect(route('app.settings.preferences'))
        ->assertSessionHas('flash.error');

    Http::assertNothingSent();
    expect(MediaSourceConnection::query()->count())->toBe(0);
});

test('without Google keys the owner is told Drive is not set up', function () {
    config()->set('services.google_media.client_id', null);

    $this->actingAs($this->owner)
        ->get(route('app.settings.drive-backup.connect'))
        ->assertRedirect(route('app.settings.preferences'))
        ->assertSessionHas('flash.error');
});

test('disconnect removes the connection and returns Backup to Keep', function () {
    driveConnectionFor($this->owner);
    $this->owner->account->forceFill(['video_policy' => VideoPolicy::Backup])->save();

    $this->actingAs($this->owner)
        ->delete(route('app.settings.drive-backup.destroy'))
        ->assertRedirect();

    expect(MediaSourceConnection::query()->count())->toBe(0)
        ->and($this->owner->account->refresh()->video_policy)->toBe(VideoPolicy::Keep);
});

test('disconnect leaves a Delete policy alone', function () {
    driveConnectionFor($this->owner);
    $this->owner->account->forceFill(['video_policy' => VideoPolicy::Delete])->save();

    $this->actingAs($this->owner)->delete(route('app.settings.drive-backup.destroy'));

    expect($this->owner->account->refresh()->video_policy)->toBe(VideoPolicy::Delete);
});

test('backup is refused without a Drive connection', function () {
    $this->actingAs($this->owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'backup'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('video_policy');

    expect($this->owner->account->refresh()->video_policy)->toBe(VideoPolicy::Keep);
});

test('backup is accepted with a working Drive connection', function () {
    driveConnectionFor($this->owner);

    $this->actingAs($this->owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'backup'])
        ->assertNoContent();

    expect($this->owner->account->refresh()->video_policy)->toBe(VideoPolicy::Backup);
});

test('backup is refused again once the connection needs reconnect', function () {
    driveConnectionFor($this->owner, ['needs_reconnect_at' => now()]);

    $this->actingAs($this->owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'backup'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('video_policy');
});

test("another user's Drive connection never counts", function () {
    driveConnectionFor($this->member);

    $this->actingAs($this->owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'backup'])
        ->assertUnprocessable();
});

test('preferences tells the owner whether Drive is connected', function (?array $state, string $expected) {
    if ($state !== null) {
        driveConnectionFor($this->owner, $state);
    }

    $this->actingAs($this->owner)
        ->get(route('app.settings.preferences'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('driveConnection', $expected));
})->with([
    'none' => [null, 'none'],
    'connected' => [[], 'connected'],
    'needs reconnect' => [['needs_reconnect_at' => now()], 'needs_reconnect'],
]);

test('preferences hides the Drive state from a member', function () {
    driveConnectionFor($this->owner);

    $this->actingAs($this->member)
        ->get(route('app.settings.preferences'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('driveConnection', null));
});

test('marking the connection as needing reconnect shows on preferences and blocks Backup', function () {
    driveConnectionFor($this->owner);

    MarkDriveNeedsReconnect::execute($this->owner->account);

    $this->actingAs($this->owner)
        ->get(route('app.settings.preferences'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('driveConnection', 'needs_reconnect'));

    $this->actingAs($this->owner)
        ->patchJson(route('app.settings.video-policy.update'), ['video_policy' => 'backup'])
        ->assertUnprocessable();
});

test('marking an account with no connection does nothing', function () {
    MarkDriveNeedsReconnect::execute($this->owner->account);

    expect(MediaSourceConnection::query()->count())->toBe(0);
});
