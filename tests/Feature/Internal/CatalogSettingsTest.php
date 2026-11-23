<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\CatalogSetting;

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
        ->assertJsonPath('updated_at', null)
        ->assertJsonMissingPath('tokens_per_credit')
        ->assertJsonMissingPath('trial_ai_credits');

    expect(CatalogSetting::query()->count())->toBe(0);
});

test('a PATCH persists the Trial and audits before and after', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.catalog-settings.update'), ['trial_days' => 14])
        ->assertOk()
        ->assertJsonPath('trial_days', 14)
        ->assertJsonMissingPath('tokens_per_credit')
        ->assertJsonMissingPath('trial_ai_credits');

    $this->assertDatabaseHas('catalog_settings', [
        'id' => CatalogSetting::SINGLETON_ID,
        'trial_days' => 14,
        'tokens_per_credit' => 150,
        'trial_ai_credits' => 1000,
    ]);

    $entry = AuditLog::where('action', 'catalog-settings.update')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('catalog_settings')
        ->and($entry->target_id)->toBe((string) CatalogSetting::SINGLETON_ID)
        ->and($entry->before)->toEqual(['trial_days' => 7])
        ->and($entry->after)->toEqual(['trial_days' => 14]);
});

test('the AI fields are no longer written through the catalog settings', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.catalog-settings.update'), [
            'trial_days' => 10,
            'tokens_per_credit' => 999,
            'trial_ai_credits' => 5,
        ])
        ->assertOk()
        ->assertJsonMissingPath('tokens_per_credit')
        ->assertJsonMissingPath('trial_ai_credits');

    $this->assertDatabaseHas('catalog_settings', [
        'id' => CatalogSetting::SINGLETON_ID,
        'trial_days' => 10,
        'tokens_per_credit' => 150,
        'trial_ai_credits' => 1000,
    ]);
});
