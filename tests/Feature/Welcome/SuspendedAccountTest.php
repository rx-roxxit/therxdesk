<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->owner->id,
        'account_id' => $this->owner->account_id,
    ]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);

    $this->account = $this->workspace->account;
    subscribeAccount($this->account);
    $this->account->forceFill(['suspended_at' => now()])->save();
    $this->owner->refresh();

    $this->member = workspaceMember($this->workspace, 'member');
});

test('the owner is sent to the suspended screen from an app page', function () {
    $this->actingAs($this->owner)
        ->get(route('app.calendar'))
        ->assertRedirect(route('app.welcome.suspended'));
});

test('a member is sent to the suspended screen from an app page', function () {
    $this->actingAs($this->member)
        ->get(route('app.calendar'))
        ->assertRedirect(route('app.welcome.suspended'));
});

test('the plan step sends both the owner and a member to the suspended screen', function (string $who) {
    $this->actingAs($this->{$who})
        ->get(route('app.welcome.plan'))
        ->assertRedirect(route('app.welcome.suspended'));
})->with(['owner', 'member']);

test('the member hold screen sends a suspended member to the suspended screen', function () {
    $this->actingAs($this->member)
        ->get(route('app.welcome.subscription-required'))
        ->assertRedirect(route('app.welcome.suspended'));
});

test('the plan portal sends a suspended owner to the suspended screen', function () {
    $this->actingAs($this->owner)
        ->get(route('app.welcome.plan.portal'))
        ->assertRedirect(route('app.welcome.suspended'));
});

test('the suspended screen renders for every member', function (string $who) {
    $this->actingAs($this->{$who})
        ->get(route('app.welcome.suspended'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome/Suspended', false));
})->with(['owner', 'member']);

test('after the suspension is lifted the screen sends people back into the app', function (string $who) {
    $this->account->forceFill([
        'suspended_at' => null,
        'suspension_reason' => null,
    ])->save();
    $this->owner->refresh();
    $this->member->refresh();

    $this->actingAs($this->{$who})
        ->get(route('app.welcome.suspended'))
        ->assertRedirect(route('app.calendar'));
})->with(['owner', 'member']);

test('a lifted suspension leaves a lapsed owner on the plan step', function () {
    $this->account->subscriptions()->delete();
    $this->account->forceFill([
        'suspended_at' => null,
        'suspension_reason' => null,
    ])->save();
    $this->owner->refresh();

    expect($this->account->fresh()->hasAppAccess())->toBeFalse();

    $this->actingAs($this->owner)
        ->get(route('app.welcome.suspended'))
        ->assertRedirect(route('app.welcome.plan'));
});

test('a lifted suspension leaves a lapsed member on the hold screen', function () {
    $this->account->subscriptions()->delete();
    $this->account->forceFill([
        'suspended_at' => null,
        'suspension_reason' => null,
    ])->save();
    $this->member->refresh();

    $this->actingAs($this->member)
        ->get(route('app.welcome.suspended'))
        ->assertRedirect(route('app.welcome.subscription-required'));
});

test('the account model stays out of the shared props on the suspended screen', function () {
    $response = $this->actingAs($this->owner)->get(route('app.welcome.suspended'));

    $response->assertOk()
        ->assertDontSee('suspension_reason');
});
