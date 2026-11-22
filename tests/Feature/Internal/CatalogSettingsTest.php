<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\CatalogSetting;
use App\Models\Plan;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];
});

test('the settings read the defaults without writing a row', function () {
    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.catalog-settings.show'))
        ->assertOk()
        ->assertJsonPath('trial_days', 7)
        ->assertJsonPath('tokens_per_credit', 150)
        ->assertJsonPath('trial_ai_credits', 1000);

    expect(CatalogSetting::query()->count())->toBe(0);
});

test('a PATCH persists the settings and audits before and after', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.catalog-settings.update'), [
            'trial_days' => 14,
            'tokens_per_credit' => 200,
        ])
        ->assertOk()
        ->assertJsonPath('trial_days', 14)
        ->assertJsonPath('tokens_per_credit', 200)
        ->assertJsonPath('trial_ai_credits', 1000);

    $this->assertDatabaseHas('catalog_settings', [
        'id' => CatalogSetting::SINGLETON_ID,
        'trial_days' => 14,
        'tokens_per_credit' => 200,
        'trial_ai_credits' => 1000,
    ]);

    $entry = AuditLog::where('action', 'catalog-settings.update')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('catalog_settings')
        ->and($entry->target_id)->toBe((string) CatalogSetting::SINGLETON_ID)
        ->and($entry->before)->toEqual(['trial_days' => 7, 'tokens_per_credit' => 150, 'trial_ai_credits' => 1000])
        ->and($entry->after)->toEqual(['trial_days' => 14, 'tokens_per_credit' => 200, 'trial_ai_credits' => 1000]);
});

test('the Trial credits cannot go above the smallest active Plan', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.catalog-settings.update'), ['trial_ai_credits' => 2501])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('trial_ai_credits');

    expect(CatalogSetting::query()->count())->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

test('a Plan cannot be created below the Trial credits', function () {
    $stripe = fakeCatalogStripe();

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.catalog-settings.update'), ['trial_ai_credits' => 2000])
        ->assertOk();

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

test('a Plan cannot be updated below the Trial credits', function () {
    $plan = Plan::where('slug', 'mini')->firstOrFail();

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.catalog-settings.update'), ['trial_ai_credits' => 2500])
        ->assertOk();

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.plans.update', $plan), ['ai_credits_per_month' => 2499])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ai_credits_per_month');

    expect($plan->fresh()->ai_credits_per_month)->toBe(2500);
});
