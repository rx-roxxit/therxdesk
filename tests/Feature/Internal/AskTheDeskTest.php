<?php

declare(strict_types=1);

use App\Ai\Agents\AskTheDeskAssistant;
use App\Enums\Ai\AiFeature;
use App\Enums\AskTheDesk\AskTheDeskRole;
use App\Models\AiCreditUsage;
use App\Models\AskTheDeskMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
        'rxdesk.ask_the_desk.daily_limit' => 50,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'test-key',
        'rxdesk.ai.default_text_model' => 'claude-test-model',
        'ai.providers.anthropic.models.text.default' => 'claude-test-model',
    ]);

    Mail::fake();

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];

    $this->siteHeaders = ['Authorization' => 'Bearer test-site-token'];
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function askTheDeskPayload(array $overrides = []): array
{
    return array_merge([
        'page_aggregates' => [
            'range' => 'last_7_days',
            'accounts' => ['total' => 12, 'new' => 3],
            'posts' => ['published' => 40, 'failed' => 2],
        ],
        'question' => 'How are we doing this week?',
    ], $overrides);
}

test('a question answers from the aggregates, stores the transcript and a zero-credit ledger row', function () {
    AskTheDeskAssistant::fake([
        new TextResponse(
            'Twelve Accounts, 40 posts published.',
            new Usage(promptTokens: 10, completionTokens: 5),
            new Meta('anthropic', 'claude-test-model'),
        ),
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.ask-the-desk'), askTheDeskPayload());

    $response->assertOk()
        ->assertJsonPath('answer', 'Twelve Accounts, 40 posts published.')
        ->assertJsonPath('limits.used', 1)
        ->assertJsonPath('limits.max', 50);

    expect($response->json('limits.resets_at'))->not->toBeNull();

    $question = AskTheDeskMessage::query()->where('role', AskTheDeskRole::User)->sole();
    $answer = AskTheDeskMessage::query()->where('role', AskTheDeskRole::Assistant)->sole();

    expect($question->content)->toBe('How are we doing this week?')
        ->and($answer->content)->toBe('Twelve Accounts, 40 posts published.');

    $ledger = AiCreditUsage::query()->where('feature', AiFeature::AskTheDesk)->sole();

    expect($ledger->credits)->toBe(0)
        ->and($ledger->account_id)->toBeNull()
        ->and($ledger->user_id)->toBeNull()
        ->and($ledger->workspace_id)->toBeNull()
        ->and($ledger->model)->toBe('claude-test-model')
        ->and($ledger->prompt_tokens)->toBe(10)
        ->and($ledger->completion_tokens)->toBe(5)
        ->and($ledger->total_tokens)->toBe(15);
});

test('the daily cap counts only today\'s user messages and answers 429 without storing anything', function () {
    $dayStart = now('UTC')->startOfDay();

    AskTheDeskMessage::factory()->count(50)->create([
        'role' => AskTheDeskRole::User,
        'created_at' => $dayStart->addMinutes(5),
    ]);
    AskTheDeskMessage::factory()->create([
        'role' => AskTheDeskRole::Assistant,
        'created_at' => $dayStart->addMinutes(5),
    ]);
    AskTheDeskMessage::factory()->create([
        'role' => AskTheDeskRole::User,
        'created_at' => $dayStart->subDay()->addHours(12),
    ]);

    AskTheDeskAssistant::fake();

    $response = $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.ask-the-desk'), askTheDeskPayload());

    $response->assertStatus(429)
        ->assertJsonPath('code', 'daily_limit')
        ->assertJsonPath('message', __('rxdesk.ask_the_desk.daily_limit', ['max' => 50]))
        ->assertJsonPath('limits.used', 50)
        ->assertJsonPath('limits.max', 50);

    expect($response->headers->get('Retry-After'))->toBe((string) $response->json('retry_after'))
        ->and($response->json('retry_after'))->toBeGreaterThan(0)
        ->and($response->json('limits.resets_at'))->not->toBeNull()
        ->and(AskTheDeskMessage::query()->count())->toBe(52)
        ->and(AiCreditUsage::query()->count())->toBe(0);

    AskTheDeskAssistant::assertNeverPrompted();
});

test('a provider failure answers a generic 502 and keeps the question without an answer', function () {
    AskTheDeskAssistant::fake(fn () => throw new AiException('provider down'));

    $response = $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.ask-the-desk'), askTheDeskPayload());

    $response->assertStatus(502)
        ->assertJsonPath('code', 'unavailable')
        ->assertJsonPath('message', __('rxdesk.ai.unavailable'));

    expect($response->getContent())
        ->not->toContain('anthropic')
        ->not->toContain('claude')
        ->not->toContain('claude-test-model');

    $ledger = AiCreditUsage::query()->where('feature', AiFeature::AskTheDesk)->sole();

    expect($ledger->credits)->toBe(0)
        ->and($ledger->account_id)->toBeNull()
        ->and($ledger->total_tokens)->toBe(0)
        ->and($ledger->model)->toBeNull();

    $messages = AskTheDeskMessage::query()->get();

    expect($messages)->toHaveCount(1)
        ->and($messages->sole()->role)->toBe(AskTheDeskRole::User);
});

test('an empty answer answers 502 and still records its ledger row', function () {
    AskTheDeskAssistant::fake([
        new TextResponse(
            '   ',
            new Usage(promptTokens: 8, completionTokens: 0),
            new Meta('anthropic', 'claude-test-model'),
        ),
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.ask-the-desk'), askTheDeskPayload());

    $response->assertStatus(502)
        ->assertJsonPath('code', 'unavailable')
        ->assertJsonPath('message', __('rxdesk.ai.unavailable'));

    expect(AskTheDeskMessage::query()->where('role', AskTheDeskRole::Assistant)->exists())->toBeFalse();

    $ledger = AiCreditUsage::query()->where('feature', AiFeature::AskTheDesk)->sole();

    expect($ledger->credits)->toBe(0)
        ->and($ledger->total_tokens)->toBe(8)
        ->and($ledger->model)->toBe('claude-test-model');
});

test('self-hosted answers 404', function () {
    config(['trypost.self_hosted' => true]);

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.ask-the-desk'), askTheDeskPayload())
        ->assertNotFound();
});

test('the site token answers 404', function () {
    $this->withHeaders($this->siteHeaders)
        ->postJson(route('internal.ask-the-desk'), askTheDeskPayload())
        ->assertNotFound();
});

test('a request without a token answers 404', function () {
    $this->postJson(route('internal.ask-the-desk'), askTheDeskPayload())
        ->assertNotFound();
});

test('the request needs the page aggregates and a question', function (array $payload, string $field) {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.ask-the-desk'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(AskTheDeskMessage::query()->count())->toBe(0)
        ->and(AiCreditUsage::query()->count())->toBe(0);
})->with([
    'no aggregates' => [['question' => 'How are we doing?'], 'page_aggregates'],
    'aggregates are not an object' => [['page_aggregates' => 'nope', 'question' => 'How are we doing?'], 'page_aggregates'],
    'no question' => [['page_aggregates' => ['accounts' => ['total' => 1]]], 'question'],
    'a question too long' => [[
        'page_aggregates' => ['accounts' => ['total' => 1]],
        'question' => str_repeat('a', 2001),
    ], 'question'],
]);

test('pruning removes messages older than 30 days and keeps newer ones', function () {
    $old = AskTheDeskMessage::factory()->create(['created_at' => now()->subDays(31)]);
    $recent = AskTheDeskMessage::factory()->create(['created_at' => now()->subDays(29)]);

    $this->artisan('rxdesk:prune-ask-the-desk')->assertSuccessful();

    expect(AskTheDeskMessage::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AskTheDeskMessage::query()->whereKey($recent->id)->exists())->toBeTrue();
});

test('the assistant has no tools and its instructions name no provider or model', function () {
    expect(is_subclass_of(AskTheDeskAssistant::class, HasTools::class))->toBeFalse();

    $instructions = (new AskTheDeskAssistant(['accounts' => ['total' => 3]]))->instructions();

    expect($instructions)->toContain('"total": 3')
        ->and(Str::contains($instructions, ['anthropic', 'claude', 'claude-test-model'], ignoreCase: true))->toBeFalse();
});
