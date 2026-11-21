<?php

declare(strict_types=1);

use App\Models\Plan;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->siteHeaders = ['Authorization' => 'Bearer test-site-token'];
});

test('the site payload is the active catalog with prices in cents at the top level', function () {
    $response = $this->withHeaders($this->siteHeaders)->getJson(route('internal.site'));

    $response->assertOk()
        ->assertJsonStructure(['plans' => [['slug', 'name', 'quota_bytes', 'ai_credits_per_month', 'monthly', 'yearly']]])
        ->assertJsonPath('plans.0.slug', 'mini')
        ->assertJsonPath('plans.0.monthly', 1190)
        ->assertJsonPath('plans.0.yearly', 11900)
        ->assertJsonPath('plans.1.slug', 'standard')
        ->assertJsonPath('plans.2.slug', 'plus')
        ->assertJsonPath('plans.3.slug', 'max')
        ->assertJsonMissingPath('data');

    expect($response->json())->toHaveKey('plans')
        ->and($response->json('plans'))->toHaveCount(4);
});

test('an archived Plan never appears', function () {
    Plan::factory()->archived()->create(['slug' => 'legacy', 'name' => 'Legacy']);

    $response = $this->withHeaders($this->siteHeaders)->getJson(route('internal.site'));

    $response->assertOk()
        ->assertJsonCount(4, 'plans')
        ->assertJsonMissing(['slug' => 'legacy']);
});
