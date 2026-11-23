<?php

declare(strict_types=1);

use App\Ai\Agents\SupportAssistant;
use App\Enums\Support\SupportMessageRole;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Availability\Availability;
use App\Support\SupportChat\SupportChatGate;
use App\Support\SupportChat\SupportChatLimits;
use Illuminate\Support\Carbon;
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

    $this->conversation = SupportConversation::query()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
        'last_message_at' => now(),
    ]);
});

function fakeSupportAnswer(): void
{
    SupportAssistant::fake([
        new TextResponse('An answer.', new Usage(promptTokens: 10, completionTokens: 5), new Meta('anthropic', 'claude-test-model')),
    ]);
}

function seedSupportQuestions(SupportConversation $conversation, int $count): void
{
    SupportMessage::factory()->count($count)->create([
        'conversation_id' => $conversation->id,
        'role' => SupportMessageRole::User,
    ]);
}

test('the 21st message in one conversation is refused and nothing is stored', function () {
    fakeSupportAnswer();
    seedSupportQuestions($this->conversation, SupportChatLimits::PER_CONVERSATION);

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $this->conversation), ['message' => 'One more'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'conversation')
        ->assertJsonPath('limits.conversation.used', SupportChatLimits::PER_CONVERSATION)
        ->assertJsonPath('limits.conversation.max', SupportChatLimits::PER_CONVERSATION);

    expect(SupportMessage::query()->count())->toBe(SupportChatLimits::PER_CONVERSATION);

    SupportAssistant::assertNeverPrompted();
});

test('the day limit stops a user at 40 messages and lifts after midnight UTC', function () {
    $this->travelTo(Carbon::parse(now('UTC')->startOfDay()->addHours(12)));

    fakeSupportAnswer();

    $other = SupportConversation::query()->create([
        'account_id' => $this->owner->account_id,
        'user_id' => $this->owner->id,
        'last_message_at' => now(),
    ]);
    seedSupportQuestions($other, SupportChatLimits::PER_DAY);

    $response = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $this->conversation), ['message' => 'One more'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'daily')
        ->assertJsonPath('limits.daily.used', SupportChatLimits::PER_DAY);

    expect($response->json('limits.daily.resets_at'))
        ->toBe(now('UTC')->startOfDay()->addDay()->toIso8601String());

    $this->travel(1)->days();

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $this->conversation), ['message' => 'A new day'])
        ->assertOk()
        ->assertJsonPath('limits.daily.used', 1);
});

test('a user may send ten messages a minute and the eleventh waits', function () {
    fakeSupportAnswer();

    foreach (range(1, SupportChatLimits::PER_MINUTE) as $index) {
        $this->actingAs($this->owner->fresh())
            ->postJson(route('app.support.messages.store', $this->conversation), ['message' => "Message {$index}"])
            ->assertOk();
    }

    $response = $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $this->conversation), ['message' => 'Too fast'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'minute');

    expect($response->json('retry_after'))->toBeGreaterThan(0)
        ->and($response->headers->get('Retry-After'))->toBe((string) $response->json('retry_after'))
        ->and(SupportMessage::query()->count())->toBe(SupportChatLimits::PER_MINUTE * 2);
});

test('a message at exactly the character limit is accepted', function () {
    fakeSupportAnswer();

    $this->actingAs($this->owner->fresh())
        ->postJson(route('app.support.messages.store', $this->conversation), [
            'message' => str_repeat('a', SupportChatLimits::MAX_CHARACTERS),
        ])
        ->assertOk();
});
