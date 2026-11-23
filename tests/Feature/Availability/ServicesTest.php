<?php

declare(strict_types=1);

use App\Enums\Availability\ServiceSwitch;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Availability\Availability;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'test-key',
    ]);

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->user->account_id,
    ]);
    $this->workspace->members()->attach($this->user->id, membershipPivot('admin'));
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    subscribeAccount($this->user->account);
});

test('AI is on while the provider key is installed and its switch is on', function () {
    $this->actingAs($this->user)
        ->get(route('app.workspace.channels'))
        ->assertInertia(fn (Assert $page) => $page->where('aiEnabled', true));
});

test('turning AI off hides the AI buttons through the shared prop', function () {
    app(Availability::class)->set(ServiceSwitch::Ai->value, false);

    $this->actingAs($this->user)
        ->get(route('app.workspace.channels'))
        ->assertInertia(fn (Assert $page) => $page->where('aiEnabled', false));
});

test('a missing AI provider key turns AI off even while the switch is on', function () {
    config(['ai.providers.anthropic.key' => null]);

    $this->actingAs($this->user)
        ->get(route('app.workspace.channels'))
        ->assertInertia(fn (Assert $page) => $page->where('aiEnabled', false));
});

test('Support chat and new signups start off when no row exists', function () {
    expect(ServiceSwitch::SupportChat->switchedOn())->toBeFalse()
        ->and(ServiceSwitch::NewSignups->switchedOn())->toBeFalse()
        ->and(ServiceSwitch::Ai->switchedOn())->toBeTrue();
});
