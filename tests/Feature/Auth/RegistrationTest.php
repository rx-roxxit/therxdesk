<?php

declare(strict_types=1);

use App\Enums\Availability\ServiceSwitch;
use App\Models\Account;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Availability\Availability;
use App\Support\Countries;

beforeEach(function (): void {
    config()->set('trypost.self_hosted', false);
    enableSignups();
});

function createTestInvite(string $email): Invite
{
    $account = Account::factory()->create();
    $owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    return Invite::factory()->create([
        'account_id' => $account->id,
        'invited_by' => $owner->id,
        'email' => $email,
        'workspaces' => [$workspace->id],
    ]);
}

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('the register page shows the closed state while new signups are switched off', function () {
    app(Availability::class)->set(ServiceSwitch::NewSignups->value, false);

    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('signupsClosed', true)
            ->where('invite', null)
        );
});

test('a registration while new signups are switched off is refused and creates no user', function () {
    app(Availability::class)->set(ServiceSwitch::NewSignups->value, false);

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'country' => 'TH',
        'locale' => 'en',
    ])->assertSessionHasErrors(['email' => __('rxdesk.signup.closed')]);

    $this->assertGuest();

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('an invite still registers a member while new signups are switched off', function () {
    app(Availability::class)->set(ServiceSwitch::NewSignups->value, false);
    $invite = createTestInvite('invitee@example.com');

    $this->get(route('register', ['invite' => $invite->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('signupsClosed', false)
            ->where('invite', $invite->id)
        );

    $this->post(route('register.store', ['invite' => $invite->id]), [
        'name' => 'Invitee',
        'email' => 'invitee@example.com',
        'password' => strongPassword(),
        'country' => 'TH',
        'locale' => 'en',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
    expect(User::where('email', 'invitee@example.com')->exists())->toBeTrue();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'country' => 'TH',
        'locale' => 'en',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();
    $response->assertRedirect(route('app.welcome', absolute: false));
});

test('new users get a default workspace on registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'country' => 'TH',
        'locale' => 'en',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->account_id)->not->toBeNull();
    expect($user->workspaces()->count())->toBe(1);
    expect($user->workspaces()->first()->name)->toBe("Test User's Workspace");
    expect($user->current_workspace_id)->toBe($user->workspaces()->first()->id);
});

test('new users do not have verified email by default', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'country' => 'TH',
        'locale' => 'en',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user->email_verified_at)->toBeNull();
});

test('new users registering via invite have verified email automatically', function () {
    $account = Account::factory()->create();
    $owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);
    $invite = Invite::factory()->create([
        'account_id' => $account->id,
        'invited_by' => $owner->id,
        'email' => 'test@example.com',
        'workspaces' => [$workspace->id],
    ]);

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'invite' => $invite->id,
        'country' => 'TH',
        'locale' => 'en',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user->email_verified_at)->not->toBeNull();
});

test('register page returns 404 when self_hosted and no pending invite in session', function () {
    config()->set('trypost.self_hosted', true);

    $response = $this->get(route('register'));

    $response->assertNotFound();
});

test('register POST returns 404 when self_hosted and no pending invite in session', function () {
    config()->set('trypost.self_hosted', true);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'locale' => 'en',
    ]);

    $response->assertNotFound();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('register page renders when self_hosted but session has pending invite', function () {
    config()->set('trypost.self_hosted', true);
    $invite = createTestInvite('invitee@example.com');

    $response = $this
        ->withSession(['pending_invite_id' => $invite->id])
        ->get(route('register'));

    $response->assertOk();
});

test('register page renders when self_hosted with invite query param and persists it to session', function () {
    config()->set('trypost.self_hosted', true);
    $invite = createTestInvite('invitee@example.com');

    $response = $this->get(route('register', ['invite' => $invite->id]));

    $response->assertOk();
    $response->assertSessionHas('pending_invite_id', $invite->id);
});

test('signup clears pending_invite_id from session', function () {
    config()->set('trypost.self_hosted', true);
    $invite = createTestInvite('invitee@example.com');

    $this->withSession(['pending_invite_id' => $invite->id])
        ->post(route('register.store'), [
            'name' => 'Invitee',
            'email' => 'invitee@example.com',
            'password' => strongPassword(),
            'locale' => 'en',
        ]);

    expect(session('pending_invite_id'))->toBeNull();
    expect(User::where('email', 'invitee@example.com')->exists())->toBeTrue();
});

test('register POST passes when self_hosted with invite query param even without prior session', function () {
    config()->set('trypost.self_hosted', true);
    $invite = createTestInvite('invitee@example.com');

    $response = $this->post(route('register.store', ['invite' => $invite->id]), [
        'name' => 'Invitee',
        'email' => 'invitee@example.com',
        'password' => strongPassword(),
        'locale' => 'en',
    ]);

    $response->assertSessionHasNoErrors();
    expect(User::where('email', 'invitee@example.com')->exists())->toBeTrue();
});

test('register works normally when not self_hosted even with pending invite in session', function () {
    config()->set('trypost.self_hosted', false);

    $response = $this
        ->withSession(['pending_invite_id' => 'invite-abc'])
        ->post(route('register.store'), [
            'name' => 'Invitee',
            'email' => 'invitee@example.com',
            'password' => strongPassword(),
            'country' => 'TH',
            'locale' => 'en',
        ]);

    $response->assertSessionHasNoErrors();
    expect(User::where('email', 'invitee@example.com')->exists())->toBeTrue();
    // Cleared regardless of mode, since signup consumes the marker.
    expect(session('pending_invite_id'))->toBeNull();
});

test('the register page sends the ISO country list in selling mode', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('countries', Countries::ISO)
        );
});

test('the register page sends no country list when self-hosted', function () {
    config()->set('trypost.self_hosted', true);
    $invite = createTestInvite('invitee@example.com');

    $this->withSession(['pending_invite_id' => $invite->id])
        ->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('countries', null));
});

test('registration refuses a blocked country with the notice and creates no user', function (string $country) {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'country' => $country,
        'locale' => 'en',
    ])->assertSessionHasErrors(['country' => __('rxdesk.country.blocked')]);

    $this->assertGuest();

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
})->with(['DE', 'FR', 'GB', 'NO', 'JE', 'IM', 'de']);

test('registration stores an allowed country on the user, uppercased', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'country' => 'th',
        'locale' => 'en',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'test@example.com')->first()->country)->toBe('TH');
});

test('registration requires a country in selling mode', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'locale' => 'en',
    ])->assertSessionHasErrors('country');

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('registration refuses a country code that is not ISO 3166-1', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'country' => 'XX',
        'locale' => 'en',
    ])->assertSessionHasErrors('country');

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});
