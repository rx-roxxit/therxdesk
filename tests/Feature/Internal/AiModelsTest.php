<?php

declare(strict_types=1);

use App\Ai\Agents\AiModelProbe;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'test-key',
        'ai.providers.anthropic.url' => 'https://api.anthropic.test/v1',
        'rxdesk.ai.default_text_model' => 'claude-haiku-5-5',
        'rxdesk.ai.default_image_model' => 'claude-haiku-5-5',
    ]);

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];
});

/**
 * @param  list<array{id: string, display_name: string}>  $models
 */
function fakeAnthropicModels(array $models): void
{
    Http::fake([
        config('ai.providers.anthropic.url').'/models*' => Http::response([
            'data' => $models,
            'has_more' => false,
            'last_id' => null,
        ]),
    ]);
}

test('the live list is filtered to priced registry models and split by slot', function () {
    configureImageCapability('claude-sonnet-5', false);

    fakeAnthropicModels([
        ['id' => 'claude-haiku-5-5', 'display_name' => 'Claude Haiku 5.5'],
        ['id' => 'claude-sonnet-5', 'display_name' => 'Claude Sonnet 5'],
        ['id' => 'claude-unknown', 'display_name' => 'Unknown'],
        ['id' => 'claude-opus-4-5-20251101', 'display_name' => 'Old Opus'],
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.models.index'))
        ->assertOk();

    $text = collect($response->json('text'))->pluck('id')->all();
    $image = collect($response->json('image'))->pluck('id')->all();

    expect($text)->toBe(['claude-haiku-5-5', 'claude-sonnet-5'])
        ->and($image)->toBe(['claude-haiku-5-5']);
});

test('a registry model that cannot take images is absent from the image slot but present in text', function () {
    configureImageCapability('claude-haiku-5-5', false);

    fakeAnthropicModels([
        ['id' => 'claude-haiku-5-5', 'display_name' => 'Claude Haiku 5.5'],
    ]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.models.index'))
        ->assertOk();

    expect(collect($response->json('text'))->pluck('id')->all())->toBe(['claude-haiku-5-5'])
        ->and($response->json('image'))->toBe([]);
});

test('every selectable text model carries a positive price', function () {
    $registry = array_keys(config('rxdesk.ai.models'));

    fakeAnthropicModels(array_map(
        fn (string $id): array => ['id' => $id, 'display_name' => $id],
        $registry,
    ));

    $text = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.models.index'))
        ->assertOk()
        ->json('text');

    expect($text)->not->toBeEmpty();

    foreach ($text as $item) {
        expect($item['input_price_per_mtok'])->toBeGreaterThan(0)
            ->and($item['output_price_per_mtok'])->toBeGreaterThan(0);
    }
});

test('a provider listing failure answers 502', function () {
    Http::fake([
        config('ai.providers.anthropic.url').'/models*' => Http::response(['error' => 'boom'], 500),
    ]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.models.index'))
        ->assertStatus(Response::HTTP_BAD_GATEWAY)
        ->assertJsonPath('message', 'The AI provider answered HTTP 500 while listing models.');
});

test('a missing provider key answers 502 without calling the provider', function () {
    config(['ai.providers.anthropic.key' => null]);
    Http::fake();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.ai.models.index'))
        ->assertStatus(Response::HTTP_BAD_GATEWAY)
        ->assertJsonPath('message', 'The AI provider key is not configured.');

    Http::assertNothingSent();
});

test('the model endpoints need the admin token', function () {
    fakeAnthropicModels([
        ['id' => 'claude-haiku-5-5', 'display_name' => 'Claude Haiku 5.5'],
    ]);

    $this->getJson(route('internal.ai.models.index'))->assertNotFound();

    $this->withHeaders(['Authorization' => 'Bearer test-site-token'])
        ->getJson(route('internal.ai.models.index'))
        ->assertNotFound();

    $this->withHeaders(['Authorization' => 'Bearer test-site-token'])
        ->postJson(route('internal.ai.models.test'), ['slot' => 'text', 'model' => 'claude-haiku-5-5'])
        ->assertNotFound();
});

test('the test endpoint makes one real call and answers ok', function () {
    AiModelProbe::fake(fn (): string => 'OK');

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.ai.models.test'), ['slot' => 'text', 'model' => 'claude-haiku-5-5'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('slot', 'text')
        ->assertJsonPath('model', 'claude-haiku-5-5');
});

test('the test endpoint refuses a model the slot cannot use', function () {
    configureImageCapability('claude-sonnet-5', false);
    AiModelProbe::fake(fn (): string => 'OK');

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.ai.models.test'), ['slot' => 'image', 'model' => 'claude-sonnet-5'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('model');

    AiModelProbe::assertNeverPrompted();
});

test('the test endpoint answers 422 when the model does not answer', function () {
    AiModelProbe::fake(fn () => throw new RuntimeException('model is gone'));

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.ai.models.test'), ['slot' => 'text', 'model' => 'claude-haiku-5-5'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonPath('message', 'The model did not answer. Nothing was saved.')
        ->assertJsonPath('error', 'model is gone');
});

function configureImageCapability(string $model, bool $accepts): void
{
    config(["rxdesk.ai.models.{$model}.accepts_image_input" => $accepts]);
}
