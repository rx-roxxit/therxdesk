<?php

declare(strict_types=1);

use App\Ai\Agents\SupportAssistant;
use App\Enums\User\Locale;
use App\Models\SupportConversation;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Availability\Availability;
use App\Support\SupportChat\SupportChatGate;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;

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

    app(Availability::class)->set(SupportChatGate::AVAILABILITY_KEY, true);
});

test('only declared app pages, the legal pages and the support address stay links', function () {
    $terms = (string) config('trypost.legal.terms_url');

    $answer = implode(' ', [
        'Open [Billing]('.route('app.billing.index').').',
        'Read the [Terms]('.$terms.').',
        'Write to [support](mailto:support@therxdesk.com).',
        'Do not open [this page](https://evil.example/phish).',
        'Nor [this one](javascript:alert(1)).',
        '![tracking](https://evil.example/pixel.png)',
        '<script>alert(1)</script>',
    ]);

    SupportAssistant::fake([
        new TextResponse($answer, new Usage(promptTokens: 10, completionTokens: 5), new Meta('anthropic', 'claude-test-model')),
    ]);

    $conversation = SupportConversation::query()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
        'last_message_at' => now(),
    ]);

    $html = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $conversation), ['message' => 'Where do I go?'])
        ->assertOk()
        ->json('messages.1.html');

    expect($html)
        ->toContain('href="'.route('app.billing.index').'"')
        ->toContain('href="'.$terms.'"')
        ->toContain('href="mailto:support@therxdesk.com"')
        ->not->toContain('evil.example')
        ->not->toContain('javascript:')
        ->not->toContain('<script')
        ->not->toContain('<img')
        ->toContain('this page');
});

test('the instructions never name the provider or the model', function () {
    $instructions = (new SupportAssistant(
        corpus: 'Help text.',
        brief: '## Workspace',
        links: [],
        locale: Locale::German,
    ))->instructions();

    expect($instructions)
        ->toContain('German (de)')
        ->not->toContain('anthropic')
        ->not->toContain('claude')
        ->not->toContain(config('rxdesk.ai.default_text_model'));
});
