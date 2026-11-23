<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Post\CreatePostTool;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Support\Availability\Availability;

beforeEach(function () {
    ['user' => $this->user, 'workspace' => $this->workspace, 'token' => $this->token] = parityContext();
    $this->account = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
        'timezone' => 'UTC',
    ]);
    $this->scheduled = [
        'status' => 'scheduled',
        'content' => 'Availability parity',
        'scheduled_at' => now()->addDays(2)->toIso8601String(),
    ];
});

function availabilityParitySwitch(bool $enabled): void
{
    app(Availability::class)->set(Platform::LinkedIn->availabilityKey(), $enabled);
}

function availabilityParityMessage(): string
{
    return __('rxdesk.availability.schedule_refused', ['network' => Platform::LinkedIn->label()]);
}

test('an unavailable network refuses a scheduled post on web, REST and MCP', function () {
    availabilityParitySwitch(false);

    $this->actingAs($this->user)
        ->post(route('app.posts.store'), [
            ...$this->scheduled,
            'destinations' => [[
                'social_account_id' => $this->account->id,
                'content_type' => 'linkedin_post',
            ]],
        ])
        ->assertSessionHasErrors(['destinations.0.social_account_id' => availabilityParityMessage()]);

    $this->withHeaders(parityApi($this->token))
        ->postJson(route('api.posts.store'), [
            ...$this->scheduled,
            'social_account_id' => $this->account->id,
            'content_type' => 'linkedin_post',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.social_account_id.0', availabilityParityMessage());

    TryPostServer::actingAs($this->user)->tool(CreatePostTool::class, [
        ...$this->scheduled,
        'social_account_id' => $this->account->id,
        'content_type' => 'linkedin_post',
    ])->assertHasErrors([availabilityParityMessage()]);

    expect(Post::query()->count())->toBe(0);
});

test('an unavailable network still accepts a draft on web, REST and MCP', function () {
    availabilityParitySwitch(false);

    $draft = ['status' => 'draft', 'content' => 'Availability draft'];

    $this->actingAs($this->user)
        ->post(route('app.posts.store'), [
            ...$draft,
            'destinations' => [[
                'social_account_id' => $this->account->id,
                'content_type' => 'linkedin_post',
            ]],
        ])
        ->assertSessionHasNoErrors();

    $this->withHeaders(parityApi($this->token))
        ->postJson(route('api.posts.store'), [
            ...$draft,
            'social_account_id' => $this->account->id,
            'content_type' => 'linkedin_post',
        ])
        ->assertCreated();

    TryPostServer::actingAs($this->user)->tool(CreatePostTool::class, [
        ...$draft,
        'social_account_id' => $this->account->id,
        'content_type' => 'linkedin_post',
    ])->assertOk();

    expect(Post::query()->where('status', 'draft')->count())->toBe(3);
});

test('switching the network back on accepts the scheduled post on web, REST and MCP', function () {
    availabilityParitySwitch(false);
    availabilityParitySwitch(true);

    $this->actingAs($this->user)
        ->post(route('app.posts.store'), [
            ...$this->scheduled,
            'destinations' => [[
                'social_account_id' => $this->account->id,
                'content_type' => 'linkedin_post',
            ]],
        ])
        ->assertSessionHasNoErrors();

    $this->withHeaders(parityApi($this->token))
        ->postJson(route('api.posts.store'), [
            'status' => 'scheduled',
            'content' => 'API scheduled',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'social_account_id' => $this->account->id,
            'content_type' => 'linkedin_post',
        ])
        ->assertCreated();

    TryPostServer::actingAs($this->user)->tool(CreatePostTool::class, [
        'status' => 'scheduled',
        'content' => 'MCP scheduled',
        'scheduled_at' => now()->addDays(4)->toIso8601String(),
        'social_account_id' => $this->account->id,
        'content_type' => 'linkedin_post',
    ])->assertOk();

    expect(Post::query()->where('status', 'scheduled')->count())->toBe(3);
});
