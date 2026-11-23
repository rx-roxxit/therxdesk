<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Models\AvailabilitySwitch;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Availability\Availability;
use App\Support\Social\PendingConnection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
    $this->workspace->members()->attach($this->user->id, membershipPivot('admin'));
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

function availabilityConnect(bool $enabled): void
{
    app(Availability::class)->set(Platform::LinkedIn->availabilityKey(), $enabled);
    app(Availability::class)->set(Platform::LinkedInPage->availabilityKey(), $enabled);
}

test('a network switched off leaves the connect card out and is listed as unavailable', function () {
    availabilityConnect(false);

    $this->actingAs($this->user)
        ->get(route('app.workspace.channels'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('connectablePlatforms', fn ($platforms): bool => ! in_array('linkedin', collect($platforms)->pluck('value')->all(), true))
            ->where('unavailablePlatforms', fn ($platforms): bool => in_array('linkedin', collect($platforms)->all(), true))
        );
});

test('switching the network back on restores its connect card', function () {
    availabilityConnect(true);

    $this->actingAs($this->user)
        ->get(route('app.workspace.channels'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('connectablePlatforms', fn ($platforms): bool => in_array('linkedin', collect($platforms)->pluck('value')->all(), true))
            ->where('unavailablePlatforms', fn ($platforms): bool => ! in_array('linkedin', collect($platforms)->all(), true))
        );
});

test('connect, the confirmation page and finish are refused while the network is off', function () {
    availabilityConnect(false);
    startSocialConnect($this->workspace, Platform::LinkedIn);

    PendingConnection::current()?->offer([
        PendingConnection::identity(
            Platform::LinkedIn,
            'li-1',
            'Brand',
            'brand',
            null,
            'profile',
            ['access_token' => 'token', 'scopes' => ['w_member_social']],
        ),
    ]);

    expect(Platform::LinkedIn->isAvailable())->toBeFalse()
        ->and(AvailabilitySwitch::query()->where('key', 'platform:linkedin')->value('enabled'))->toBeFalse();

    $this->actingAs($this->user)
        ->get(route('app.social.linkedin.connect'))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('app.social.connect.show', Platform::LinkedIn))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->post(route('app.social.connect.finish', Platform::LinkedIn), ['identities' => ['linkedin:li-1']])
        ->assertForbidden();
});

test('disconnecting an already connected network still works while it is off', function () {
    availabilityConnect(false);
    $account = SocialAccount::factory()->linkedin()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->delete(route('app.channels.disconnect', $account))
        ->assertRedirect();

    expect(SocialAccount::query()->find($account->id))->toBeNull();
});
