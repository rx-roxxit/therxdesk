<?php

declare(strict_types=1);

use App\Actions\User\CreateUser;
use App\Models\Account;
use App\Models\Invite;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->inviterAccount = Account::factory()->create();
    $this->inviter = User::factory()->create(['account_id' => $this->inviterAccount->id]);
    $this->inviterAccount->update(['owner_id' => $this->inviter->id]);
    $this->inviterWorkspace = Workspace::factory()->create([
        'account_id' => $this->inviterAccount->id,
        'user_id' => $this->inviter->id,
    ]);
});

function signUp(array $data): User
{
    return User::query()->findOrFail(CreateUser::execute($data)->id);
}

function inviteTo(User $inviter, Workspace $workspace, string $email): Invite
{
    return Invite::factory()->create([
        'account_id' => $workspace->account_id,
        'invited_by' => $inviter->id,
        'email' => $email,
        'workspaces' => [$workspace->id],
    ]);
}

test('an Owner whose account has workspaces cannot accept another Owner\'s Invite', function () {
    $owner = signUp(['name' => 'Second Owner', 'email' => 'second@example.com', 'password' => 'password']);
    $ownedAccountId = $owner->account_id;
    $ownedWorkspaceIds = $owner->account->workspaces()->pluck('id')->all();
    $membershipCount = $owner->workspaces()->count();
    $invite = inviteTo($this->inviter, $this->inviterWorkspace, 'second@example.com');

    $this->actingAs($owner)
        ->post(route('app.invites.accept', $invite))
        ->assertRedirect(route('app.calendar'))
        ->assertSessionHas('flash.bannerStyle', 'danger')
        ->assertSessionHas('flash.banner', __('rxdesk.invite.owner_has_workspaces'));

    $owner->refresh();

    expect($owner->account_id)->toBe($ownedAccountId)
        ->and(Account::find($ownedAccountId)->owner_id)->toBe($owner->id)
        ->and(Account::find($ownedAccountId)->workspaces()->pluck('id')->all())->toBe($ownedWorkspaceIds)
        ->and($owner->workspaces()->count())->toBe($membershipCount)
        ->and($this->inviterWorkspace->members()->where('user_id', $owner->id)->exists())->toBeFalse()
        ->and($invite->refresh()->accepted_at)->toBeNull();
});

test('the refusal message tells the Owner to join with a different email as a Member', function () {
    expect(__('rxdesk.invite.owner_has_workspaces'))
        ->toBe("You own workspaces with this email, so you can't join another account with it. Join as a Member with a different email instead.");
});

test('signup through an Invite still accepts normally', function () {
    $invitee = signUp([
        'name' => 'New Member',
        'email' => 'member@example.com',
        'password' => 'password',
        'is_invite' => true,
    ]);
    $shellAccountId = $invitee->account_id;
    $invite = inviteTo($this->inviter, $this->inviterWorkspace, 'member@example.com');

    $this->actingAs($invitee)
        ->post(route('app.invites.accept', $invite))
        ->assertRedirect(route('app.calendar'))
        ->assertSessionHas('flash.bannerStyle', 'success');

    expect($invitee->refresh()->account_id)->toBe($this->inviterAccount->id)
        ->and(Account::find($shellAccountId))->toBeNull()
        ->and($this->inviterWorkspace->members()->where('user_id', $invitee->id)->exists())->toBeTrue()
        ->and($invite->refresh()->accepted_at)->not->toBeNull();
});

test('a Member already on the inviting account accepts further Invites', function () {
    $member = User::factory()->create(['email' => 'member@example.com', 'account_id' => $this->inviterAccount->id]);
    $this->inviterWorkspace->members()->attach($member->id, ['is_admin' => false, 'requires_approval' => false]);
    $second = Workspace::factory()->create([
        'account_id' => $this->inviterAccount->id,
        'user_id' => $this->inviter->id,
    ]);
    $invite = inviteTo($this->inviter, $second, 'member@example.com');

    $this->actingAs($member)
        ->post(route('app.invites.accept', $invite))
        ->assertSessionHas('flash.bannerStyle', 'info');

    expect($member->refresh()->account_id)->toBe($this->inviterAccount->id)
        ->and($second->members()->where('user_id', $member->id)->exists())->toBeTrue()
        ->and($invite->refresh()->accepted_at)->not->toBeNull();
});

test('an Owner whose account has no workspaces left can accept', function () {
    $owner = signUp(['name' => 'Empty Owner', 'email' => 'empty@example.com', 'password' => 'password']);
    $ownedAccountId = $owner->account_id;
    $owner->account->workspaces()->get()->each->delete();
    $invite = inviteTo($this->inviter, $this->inviterWorkspace, 'empty@example.com');

    $this->actingAs($owner)
        ->post(route('app.invites.accept', $invite))
        ->assertSessionHas('flash.bannerStyle', 'success');

    expect($owner->refresh()->account_id)->toBe($this->inviterAccount->id)
        ->and(Account::find($ownedAccountId))->toBeNull()
        ->and($invite->refresh()->accepted_at)->not->toBeNull();
});
