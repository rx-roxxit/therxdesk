<?php

declare(strict_types=1);

use App\Enums\Account\VideoPolicy;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function memberOfAccountOf(User $owner): User
{
    return User::factory()->create(['account_id' => $owner->account_id]);
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
