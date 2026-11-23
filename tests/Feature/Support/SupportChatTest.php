<?php

declare(strict_types=1);

use App\Ai\Agents\SupportAssistant;
use App\Enums\Ai\AiFeature;
use App\Enums\Alerts\AdminAlertKey;
use App\Enums\Alerts\AlertLevel;
use App\Enums\Support\SupportFeedback;
use App\Enums\Support\SupportMessageRole;
use App\Enums\User\Locale;
use App\Mail\AdminAlert as AdminAlertMail;
use App\Models\AiCreditUsage;
use App\Models\Plan;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiCredits;
use App\Support\Availability\Availability;
use App\Support\SupportChat\HelpCorpus;
use App\Support\SupportChat\SupportChatGate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'test-key',
        'rxdesk.ai.default_text_model' => 'claude-test-model',
        'ai.providers.anthropic.models.text.default' => 'claude-test-model',
    ]);

    $this->plan = Plan::factory()->create(['name' => 'Standard', 'ai_credits_per_month' => 100]);

    $this->owner = User::factory()->create([
        'name' => 'Owner Person',
        'email' => 'owner@example.com',
        'locale' => Locale::English,
    ]);

    $this->account = $this->owner->account->fresh();
    $this->account->update(['plan_id' => $this->plan->id]);

    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);
    $this->workspace->members()->attach($this->owner->id, membershipPivot('admin'));
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);
    subscribeAccount($this->account);

    app(Availability::class)->set(SupportChatGate::AVAILABILITY_KEY, true);
});

function supportAnswer(string $text = 'Here is some **help**.'): TextResponse
{
    return new TextResponse($text, new Usage(promptTokens: 200, completionTokens: 100), new Meta('anthropic', 'claude-test-model'));
}

function startSupportConversation(User $user): SupportConversation
{
    return SupportConversation::query()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
        'last_message_at' => now(),
    ]);
}

test('a message stores the question and the answer and writes a zero-credit ledger row', function () {
    SupportAssistant::fake([supportAnswer()]);

    AiCreditUsage::factory()->create([
        'account_id' => $this->account->id,
        'feature' => AiFeature::WritingAssistant,
        'credits' => 5,
    ]);

    $conversation = startSupportConversation($this->owner->fresh());

    $response = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $conversation), [
            'message' => 'How do I schedule a post?',
            'page' => '/posts',
        ])
        ->assertOk()
        ->assertJsonPath('messages.0.role', 'user')
        ->assertJsonPath('messages.0.text', 'How do I schedule a post?')
        ->assertJsonPath('messages.1.role', 'assistant')
        ->assertJsonPath('messages.1.feedback', null)
        ->assertJsonPath('limits.conversation.used', 1)
        ->assertJsonPath('limits.daily.used', 1);

    expect($response->json('messages.1.html'))->toContain('<strong>help</strong>');

    $response->assertDontSee('anthropic')->assertDontSee('claude-test-model');

    $messages = SupportMessage::query()->orderBy('created_at')->get();

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->role)->toBe(SupportMessageRole::User)
        ->and($messages[1]->role)->toBe(SupportMessageRole::Assistant)
        ->and($messages[1]->content)->toBe('Here is some **help**.');

    expect($conversation->fresh()->last_page)->toBe('/posts');

    $ledger = AiCreditUsage::query()->where('feature', AiFeature::SupportChat)->sole();

    expect($ledger->credits)->toBe(0)
        ->and($ledger->total_tokens)->toBe(300)
        ->and(app(AiCredits::class)->used($this->account->fresh()))->toBe(5);
});

test('the last conversation reopens within a day and New conversation starts another', function () {
    $conversation = startSupportConversation($this->owner->fresh());

    SupportMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => SupportMessageRole::User,
        'content' => 'An earlier question',
    ]);

    $conversation->forceFill(['last_message_at' => now()->subHours(SupportConversation::RESUME_HOURS - 1)])->save();

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.support.conversations.index'))
        ->assertOk()
        ->assertJsonPath('conversation.id', $conversation->id)
        ->assertJsonPath('messages.0.text', 'An earlier question');

    $conversation->forceFill(['last_message_at' => now()->subHours(SupportConversation::RESUME_HOURS + 1)])->save();

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.support.conversations.index'))
        ->assertOk()
        ->assertJsonPath('conversation', null);

    $newId = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.conversations.store'))
        ->assertOk()
        ->assertJsonPath('conversation.id', fn (string $id): bool => $id !== $conversation->id)
        ->json('conversation.id');

    $this->actingAs($this->owner->fresh())
        ->getJson(route('app.support.conversations.index'))
        ->assertOk()
        ->assertJsonPath('conversation.id', $newId);
});

test('the Owner brief carries the Plan and Storage but never the name or email', function () {
    SupportAssistant::fake([supportAnswer()]);

    $conversation = startSupportConversation($this->owner->fresh());

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $conversation), ['message' => 'Where is my plan?'])
        ->assertOk();

    SupportAssistant::assertPrompted(function (AgentPrompt $prompt): bool {
        expect($prompt->agent->brief)
            ->toContain('## Account')
            ->toContain('Plan: Standard')
            ->toContain('Storage:')
            ->toContain('AI credits:')
            ->not->toContain('owner@example.com')
            ->not->toContain('Owner Person');

        expect($prompt->agent->instructions())->toContain(app(HelpCorpus::class)->prompt());

        return true;
    });
});

test('a Member brief carries workspace facts only', function () {
    SupportAssistant::fake([supportAnswer()]);

    $member = workspaceMember($this->workspace, 'member', [
        'name' => 'Member Person',
        'email' => 'member@example.com',
    ]);

    $conversation = startSupportConversation($member);

    $this->actingAs($member)
        ->postJson(route('app.support.messages.store', $conversation), ['message' => 'How do I connect a channel?'])
        ->assertOk();

    SupportAssistant::assertPrompted(function (AgentPrompt $prompt): bool {
        expect($prompt->agent->brief)
            ->toContain('## Workspace')
            ->not->toContain('## Account')
            ->not->toContain('Plan:')
            ->not->toContain('Storage:')
            ->not->toContain('AI credits:')
            ->not->toContain('owner@example.com')
            ->not->toContain('Member Person');

        return true;
    });
});

test('an answer is capped at about 700 tokens and has no tools', function () {
    $attributes = (new ReflectionClass(SupportAssistant::class))->getAttributes(MaxTokens::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->value)->toBe(700)
        ->and(is_subclass_of(SupportAssistant::class, HasTools::class))->toBeFalse();
});

test('a provider failure stores the question, spends nothing and raises an urgent alert', function () {
    Mail::fake();
    SupportAssistant::fake([fn () => throw new ConnectionException('timeout')]);

    $conversation = startSupportConversation($this->owner->fresh());

    $response = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $conversation), ['message' => 'Help me please'])
        ->assertStatus(Response::HTTP_BAD_GATEWAY)
        ->assertJsonPath('code', 'unavailable');

    $response->assertDontSee('anthropic')->assertDontSee('claude-test-model');

    expect(SupportMessage::query()->where('role', SupportMessageRole::Assistant)->count())->toBe(0)
        ->and(AiCreditUsage::query()->count())->toBe(0)
        ->and(app(AiCredits::class)->used($this->account->fresh()))->toBe(0);

    Mail::assertQueued(AdminAlertMail::class, function (AdminAlertMail $mail): bool {
        expect($mail->level)->toBe(AlertLevel::Urgent)
            ->and($mail->key)->toBe(AdminAlertKey::AiProviderFailure)
            ->and($mail->context['error'])->toBe('timeout');

        return true;
    });
});

test('a failed question can be retried without sending it again', function () {
    SupportAssistant::fake([fn () => throw new ConnectionException('timeout')]);

    $conversation = startSupportConversation($this->owner->fresh());

    $messageId = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $conversation), ['message' => 'Help me please'])
        ->assertStatus(Response::HTTP_BAD_GATEWAY)
        ->json('message_id');

    SupportAssistant::fake([supportAnswer('Recovered answer.')]);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.retry', $messageId))
        ->assertOk()
        ->assertJsonPath('messages.1.role', 'assistant')
        ->assertJsonPath('messages.1.html', fn (string $html): bool => trim($html) === '<p>Recovered answer.</p>');

    expect(SupportMessage::query()->where('role', SupportMessageRole::User)->count())->toBe(1)
        ->and(SupportMessage::query()->where('role', SupportMessageRole::Assistant)->count())->toBe(1);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.retry', $messageId))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'not_retryable');
});

test('feedback is kept on an answer and cleared again', function () {
    SupportAssistant::fake([supportAnswer()]);

    $conversation = startSupportConversation($this->owner->fresh());

    $answerId = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $conversation), ['message' => 'A question'])
        ->json('messages.1.id');

    $this->actingAs($this->owner->fresh())
        ->patchJson(route('app.support.messages.feedback', $answerId), ['feedback' => 'up'])
        ->assertOk()
        ->assertJsonPath('message.feedback', 'up');

    expect(SupportMessage::query()->find($answerId)->feedback)->toBe(SupportFeedback::Up);

    $this->actingAs($this->owner->fresh())
        ->patchJson(route('app.support.messages.feedback', $answerId), ['feedback' => null])
        ->assertOk()
        ->assertJsonPath('message.feedback', null);

    expect(SupportMessage::query()->find($answerId)->feedback)->toBeNull();
});

test('another user cannot read or write a conversation', function () {
    SupportAssistant::fake([supportAnswer()]);

    $conversation = startSupportConversation($this->owner->fresh());

    $messageId = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $conversation), ['message' => 'Private question'])
        ->json('messages.1.id');

    $member = workspaceMember($this->workspace, 'member');

    $this->actingAs($member)
        ->postJson(route('app.support.messages.store', $conversation), ['message' => 'Peek'])
        ->assertNotFound();

    $this->actingAs($member)
        ->patchJson(route('app.support.messages.feedback', $messageId), ['feedback' => 'down'])
        ->assertNotFound();

    $this->actingAs($member)
        ->getJson(route('app.support.conversations.index'))
        ->assertOk()
        ->assertJsonPath('conversation', null);
});

test('a message must be text inside the character limit and a page must be a path', function (array $payload, string $field) {
    $conversation = startSupportConversation($this->owner->fresh());

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $conversation), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'no message' => [['message' => ''], 'message'],
    'too long' => [['message' => str_repeat('a', 2001)], 'message'],
    'page is not a path' => [['message' => 'Hello', 'page' => 'https://example.com'], 'page'],
]);
