<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use App\Support\Availability\Availability;
use App\Support\SupportChat\SupportChatGate;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'test-key',
    ]);

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    subscribeAccount($this->owner->account);

    $this->availability = app(Availability::class);
});

function switchSupportChatOn(Availability $availability): void
{
    $availability->set(SupportChatGate::AVAILABILITY_KEY, true);
}

test('the chat route is unknown while the System admin has not switched it on', function () {
    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.support.conversations.index'))
        ->assertNotFound();

    $this->actingAs($this->owner->fresh())
        ->get(route('app.calendar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('supportChat', false));
});

test('switching the Support chat on opens the route and the Help menu item', function () {
    switchSupportChatOn($this->availability);

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.support.conversations.index'))
        ->assertOk()
        ->assertJsonPath('conversation', null);

    $this->actingAs($this->owner->fresh())
        ->get(route('app.calendar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('supportChat', true));
});

test('switching it off again closes both', function () {
    switchSupportChatOn($this->availability);
    $this->availability->set(SupportChatGate::AVAILABILITY_KEY, false);

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.support.conversations.index'))
        ->assertNotFound();

    $this->actingAs($this->owner->fresh())
        ->get(route('app.calendar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('supportChat', false));
});

test('a self-hosted install never has the chat', function (Closure $configure) {
    $configure();
    switchSupportChatOn($this->availability);

    expect(app(SupportChatGate::class)->enabled())->toBeFalse();

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.support.conversations.index'))
        ->assertNotFound();
})->with([
    'self-hosted' => [fn () => config(['trypost.self_hosted' => true])],
    'no AI key' => [fn () => config(['ai.providers.anthropic.key' => null])],
    'AI switched off' => [fn () => app(Availability::class)->set(SupportChatGate::AI_AVAILABILITY_KEY, false)],
]);

test('a guest cannot reach the chat', function () {
    switchSupportChatOn($this->availability);

    $this->getJson(route('app.support.conversations.index'))
        ->assertUnauthorized();
});
