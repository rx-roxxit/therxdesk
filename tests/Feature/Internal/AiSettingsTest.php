<?php

declare(strict_types=1);

use App\Ai\Agents\AiModelProbe;
use App\Models\AuditLog;
use App\Models\CatalogSetting;
use App\Models\Offer;
use App\Models\Plan;

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

$getSettings = function () {
    return test()->withHeaders(test()->adminHeaders)->getJson(route('internal.ai.settings.show'));
};

$patchSettings = function (array $payload) {
    return test()->withHeaders(test()->adminHeaders)->patchJson(route('internal.ai.settings.update'), $payload);
};

test('the AI settings read the defaults without writing a row', function () use ($getSettings) {
    $getSettings()
        ->assertOk()
        ->assertJsonPath('tokens_per_credit', 150)
        ->assertJsonPath('trial_ai_credits', 1000)
        ->assertJsonPath('text_model', 'claude-haiku-5-5')
        ->assertJsonPath('image_model', 'claude-haiku-5-5')
        ->assertJsonPath('provider', 'anthropic')
        ->assertJsonPath('key_configured', true)
        ->assertJsonPath('updated_at', null)
        ->assertJsonPath('cost.tokens_per_credit', 150)
        ->assertJsonPath('cost.text_model', 'claude-haiku-5-5');

    expect(CatalogSetting::query()->count())->toBe(0);
});

test('a token-rate PATCH persists and records one ai-settings audit row', function () use ($patchSettings) {
    $patchSettings(['tokens_per_credit' => 200])
        ->assertOk()
        ->assertJsonPath('tokens_per_credit', 200)
        ->assertJsonPath('text_model', 'claude-haiku-5-5');

    $this->assertDatabaseHas('catalog_settings', [
        'id' => CatalogSetting::SINGLETON_ID,
        'tokens_per_credit' => 200,
        'trial_ai_credits' => 1000,
    ]);

    $entry = AuditLog::where('action', 'ai-settings.update')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('catalog_settings')
        ->and($entry->target_id)->toBe((string) CatalogSetting::SINGLETON_ID)
        ->and($entry->before)->toEqual([
            'tokens_per_credit' => 150,
            'trial_ai_credits' => 1000,
            'text_model' => null,
            'image_model' => null,
        ])
        ->and($entry->after)->toEqual([
            'tokens_per_credit' => 200,
            'trial_ai_credits' => 1000,
            'text_model' => null,
            'image_model' => null,
        ]);
});

test('a save that changes nothing records no audit row and writes no row', function () use ($patchSettings) {
    $patchSettings(['tokens_per_credit' => 150])->assertOk();

    expect(CatalogSetting::query()->count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

test('a changed image model runs exactly one probe and saves', function () use ($patchSettings) {
    $probes = [];

    AiModelProbe::fake(function (string $prompt, mixed $attachments, mixed $provider, string $model) use (&$probes): string {
        $probes[] = $model;

        return 'OK';
    });

    $patchSettings(['image_model' => 'claude-sonnet-5'])
        ->assertOk()
        ->assertJsonPath('image_model', 'claude-sonnet-5');

    expect($probes)->toBe(['claude-sonnet-5'])
        ->and(CatalogSetting::current()->image_model)->toBe('claude-sonnet-5');

    $entry = AuditLog::where('action', 'ai-settings.update')->sole();

    expect($entry->after['image_model'])->toBe('claude-sonnet-5');
});

test('a failing probe answers 422, keeps the old model and records nothing', function () use ($patchSettings) {
    AiModelProbe::fake(fn () => throw new RuntimeException('model is gone'));

    $patchSettings(['image_model' => 'claude-sonnet-5'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The model did not answer. Nothing was saved.')
        ->assertJsonPath('error', 'model is gone');

    expect(CatalogSetting::query()->count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

test('a change that pushes a Plan past ten percent needs confirmation', function () use ($patchSettings) {
    AiModelProbe::fake(fn (): string => 'OK');

    $response = $patchSettings(['text_model' => 'claude-opus-5-5'])
        ->assertStatus(422)
        ->assertJsonPath('requires_confirmation', true)
        ->assertJsonPath('cost.text_model', 'claude-opus-5-5');

    $mini = collect($response->json('cost.plans'))->firstWhere('slug', 'mini');

    expect($mini['over_threshold'])->toBeTrue()
        ->and($mini['worst_case_cents'])->toBe(750)
        ->and(CatalogSetting::query()->count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

test('the same change with confirm true saves and audits', function () use ($patchSettings) {
    AiModelProbe::fake(fn (): string => 'OK');

    $patchSettings(['text_model' => 'claude-opus-5-5', 'confirm' => true])
        ->assertOk()
        ->assertJsonPath('text_model', 'claude-opus-5-5');

    expect(CatalogSetting::current()->text_model)->toBe('claude-opus-5-5');

    $entry = AuditLog::where('action', 'ai-settings.update')->sole();

    expect($entry->after['text_model'])->toBe('claude-opus-5-5');
});

test('the Trial credits cannot go above the smallest active Plan', function () use ($patchSettings) {
    $patchSettings(['trial_ai_credits' => 2501])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('trial_ai_credits');

    expect(CatalogSetting::query()->count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

test('a model that cannot read images is refused for the image slot', function () use ($patchSettings) {
    config(['rxdesk.ai.models.claude-sonnet-5.accepts_image_input' => false]);

    AiModelProbe::fake(fn (): string => 'OK');

    $patchSettings(['image_model' => 'claude-sonnet-5'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image_model');

    expect(CatalogSetting::query()->count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

test('the cost table follows the Plan credits, the token rate, the text model and a general Offer', function () use ($getSettings, $patchSettings) {
    $initial = $getSettings()->assertOk()->json('cost');

    $mini = collect($initial['plans'])->firstWhere('slug', 'mini');

    expect((float) $initial['output_price_per_mtok'])->toBe(0.5)
        ->and($mini['monthly_cents'])->toBe(1190)
        ->and($mini['discounted_monthly_cents'])->toBe(1190)
        ->and($mini['threshold_cents'])->toBe(119)
        ->and($mini['worst_case_cents'])->toBe(19)
        ->and($mini['over_threshold'])->toBeFalse();

    $patchSettings(['tokens_per_credit' => 200])->assertOk();

    $afterTokens = collect($getSettings()->json('cost.plans'))->firstWhere('slug', 'mini');

    expect($afterTokens['worst_case_cents'])->toBe(25);

    Plan::where('slug', 'mini')->firstOrFail()->update(['ai_credits_per_month' => 5000]);

    $afterCredits = collect($getSettings()->json('cost.plans'))->firstWhere('slug', 'mini');

    expect($afterCredits['worst_case_cents'])->toBe(50);

    AiModelProbe::fake(fn (): string => 'OK');

    $patchSettings(['text_model' => 'claude-opus-5-5', 'confirm' => true])->assertOk();

    $afterModel = $getSettings()->json('cost');

    expect((float) $afterModel['output_price_per_mtok'])->toBe(20.0)
        ->and($afterModel['text_model'])->toBe('claude-opus-5-5');

    Offer::factory()->general()->percent(50)->create();

    $afterOffer = $getSettings()->json('cost');
    $offeredMini = collect($afterOffer['plans'])->firstWhere('slug', 'mini');

    expect($afterOffer['general_offer'])->not->toBeNull()
        ->and($offeredMini['monthly_cents'])->toBe(1190)
        ->and($offeredMini['discounted_monthly_cents'])->toBe(595)
        ->and($offeredMini['threshold_cents'])->toBe(59)
        ->and($offeredMini['worst_case_cents'])->toBe(2000)
        ->and($offeredMini['over_threshold'])->toBeTrue()
        ->and($afterOffer['over_threshold'])->toBeTrue();
});

test('a Plan cannot be created below the Trial credits', function () use ($patchSettings) {
    $stripe = fakeCatalogStripe();

    $patchSettings(['trial_ai_credits' => 2000])->assertOk();

    $stripe->requests = [];

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.plans.store'), [
            'slug' => 'tiny',
            'name' => 'Tiny',
            'quota_bytes' => 1024 ** 3,
            'ai_credits_per_month' => 1999,
            'monthly_amount' => 500,
            'sort' => 5,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ai_credits_per_month');

    expect($stripe->requests)->toBe([])
        ->and(Plan::where('slug', 'tiny')->exists())->toBeFalse();
});

test('a Plan cannot be updated below the Trial credits', function () use ($patchSettings) {
    $plan = Plan::where('slug', 'mini')->firstOrFail();

    $patchSettings(['trial_ai_credits' => 2500])->assertOk();

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.plans.update', $plan), ['ai_credits_per_month' => 2499])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ai_credits_per_month');

    expect($plan->fresh()->ai_credits_per_month)->toBe(2500);
});
