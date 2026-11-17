<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Invite;
use App\Models\SignupCode;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => config()->set('trypost.self_hosted', true));

/**
 * @param  array<string, mixed>  $overrides
 */
function registerWithSignupCode(array $overrides = []): TestResponse
{
    return test()->post(route('register.store'), array_merge([
        'name' => 'Ann',
        'email' => 'ann@example.com',
        'password' => strongPassword(),
        'locale' => 'en',
    ], $overrides));
}

function inviteFromAnotherOwner(string $email): Invite
{
    $owner = User::factory()->create();
    Workspace::factory()->create(['account_id' => $owner->account_id, 'user_id' => $owner->id]);

    return Invite::factory()->create([
        'account_id' => $owner->account_id,
        'invited_by' => $owner->id,
        'email' => $email,
    ]);
}

test('the signup page opens without an invite and asks for a signup code', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/Register')
            ->where('signupCodeRequired', true));
});

test('the signup page opened from an invite asks for no code', function () {
    $invite = inviteFromAnotherOwner('member@example.com');

    $this->get(route('register', ['invite' => $invite->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('signupCodeRequired', false));
});

test('a valid code makes the new person the Owner of their own account and first workspace', function () {
    $signupCode = SignupCode::factory()->create(['code' => 'ABCD-EFGH-JKMN']);

    registerWithSignupCode(['signup_code' => ' abcd-efgh-jkmn '])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('app.welcome'));

    $owner = User::where('email', 'ann@example.com')->sole();
    $signupCode->refresh();

    expect($owner->account->owner_id)->toBe($owner->id)
        ->and(Workspace::where('account_id', $owner->account_id)->pluck('name')->all())->toBe(["Ann's Workspace"])
        ->and($signupCode->used_by_user_id)->toBe($owner->id)
        ->and($signupCode->used_at)->not->toBeNull()
        ->and($this->isAuthenticated())->toBeTrue();
});

test('signup without an invite is refused when the code is missing, wrong, used or revoked', function (?string $sentCode, Closure $issueCode, string $message) {
    $issueCode();
    $accountsBefore = Account::count();

    registerWithSignupCode(['signup_code' => $sentCode])
        ->assertSessionHasErrors(['signup_code' => $message]);

    expect(User::where('email', 'ann@example.com')->exists())->toBeFalse()
        ->and(Account::count())->toBe($accountsBefore);
})->with([
    'missing' => [null, fn () => SignupCode::factory()->create(), 'Enter the signup code the admin gave you.'],
    'wrong' => ['WXYZ-WXYZ-WXYZ', fn () => SignupCode::factory()->create(), "This signup code doesn't exist. Check it and try again."],
    'used' => ['ABCD-EFGH-JKMN', fn () => SignupCode::factory()->usedBy(User::factory()->create())->create(['code' => 'ABCD-EFGH-JKMN']), 'This signup code was already used. Ask the admin for a new one.'],
    'revoked' => ['ABCD-EFGH-JKMN', fn () => SignupCode::factory()->revoked()->create(['code' => 'ABCD-EFGH-JKMN']), 'This signup code was revoked. Ask the admin for a new one.'],
]);

test('a Member signs up through their workspace invite without a code', function () {
    $invite = inviteFromAnotherOwner('member@example.com');

    registerWithSignupCode(['email' => 'member@example.com', 'invite' => $invite->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('app.invites.show', $invite));

    expect(User::where('email', 'member@example.com')->exists())->toBeTrue()
        ->and(SignupCode::count())->toBe(0);
});

test('an invite kept in the session alone does not stand in for a code', function () {
    $invite = inviteFromAnotherOwner('member@example.com');

    $this->withSession(['pending_invite_id' => $invite->id])
        ->post(route('register.store'), [
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => strongPassword(),
            'locale' => 'en',
        ])
        ->assertSessionHasErrors('signup_code');

    expect(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
});
