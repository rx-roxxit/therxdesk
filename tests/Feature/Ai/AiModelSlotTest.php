<?php

declare(strict_types=1);

use App\Ai\Agents\MediaAltTextGenerator;
use App\Ai\Agents\PostWritingAssistant;
use App\Models\CatalogSetting;
use App\Models\Media;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.models.text.default' => 'claude-haiku-5-5',
        'rxdesk.ai.default_text_model' => 'claude-haiku-5-5',
        'rxdesk.ai.default_image_model' => 'claude-haiku-5-5',
    ]);

    $plan = Plan::factory()->create([
        'ai_credits_per_month' => 1000,
        'workspace_limit' => null,
    ]);

    $this->owner = User::factory()->create();
    $this->account = $this->owner->account->fresh();
    $this->account->update(['plan_id' => $plan->id]);
    $this->account = $this->account->fresh();
    subscribeAccount($this->account);

    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));

    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    $this->owner = $this->owner->fresh();
});

test('the writing assistant uses the text slot model', function () {
    CatalogSetting::current()->forceFill(['text_model' => 'claude-sonnet-5'])->save();

    PostWritingAssistant::fake(['A caption.']);

    $this->actingAs($this->owner)
        ->postJson(route('app.posts.ai.assist'), [
            'mode' => 'generate',
            'prompt' => 'announce our summer launch today',
        ])
        ->assertOk();

    PostWritingAssistant::assertPrompted(
        fn (AgentPrompt $prompt): bool => $prompt->model === 'claude-sonnet-5',
    );
});

test('alt text uses the image slot model', function () {
    CatalogSetting::current()->forceFill(['image_model' => 'claude-opus-5-5'])->save();

    MediaAltTextGenerator::fake(['A chart.']);
    $asset = Media::factory()->temporaryUpload($this->workspace)->create();

    $this->actingAs($this->owner)
        ->postJson(route('app.posts.ai.alt-text'), ['media_id' => $asset->id])
        ->assertOk();

    MediaAltTextGenerator::assertPrompted(
        fn (AgentPrompt $prompt): bool => $prompt->model === 'claude-opus-5-5',
    );
});

test('an empty slot falls back to the Config default', function () {
    PostWritingAssistant::fake(['A caption.']);

    $this->actingAs($this->owner)
        ->postJson(route('app.posts.ai.assist'), [
            'mode' => 'generate',
            'prompt' => 'announce our summer launch today',
        ])
        ->assertOk();

    PostWritingAssistant::assertPrompted(
        fn (AgentPrompt $prompt): bool => $prompt->model === 'claude-haiku-5-5',
    );
});
