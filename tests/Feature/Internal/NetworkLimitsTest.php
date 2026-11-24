<?php

declare(strict_types=1);

use App\Models\AccountNetworkCall;
use App\Models\AuditLog;
use App\Models\NetworkCallBucket;
use App\Models\NetworkLimitSetting;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.network_limits.enabled' => true,
        'rxdesk.network_limits.buckets.youtube' => 100,
        'rxdesk.network_limits.buckets.tiktok' => 100,
        'rxdesk.network_limits.buckets.pinterest' => 100,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];
});

test('the show endpoint reports each bucket with its percent and status', function () {
    NetworkCallBucket::factory()->create(['network' => 'tiktok', 'calls' => 70]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.network-limits.show'))
        ->assertOk();

    $tiktok = collect($response->json('networks'))->firstWhere('network', 'tiktok');

    expect($response->json('date'))->toBe(CarbonImmutable::now('UTC')->toDateString())
        ->and($response->json('reset_at'))->toBe(CarbonImmutable::now('UTC')->startOfDay()->addDay()->toIso8601String())
        ->and($response->json('thresholds'))->toBe([
            'today_percent' => 70,
            'urgent_percent' => 90,
            'account_digest_percent' => 10,
        ])
        ->and($tiktok['calls'])->toBe(70)
        ->and($tiktok['capacity'])->toBe(100)
        ->and($tiktok['percent'])->toBe(70)
        ->and($tiktok['status'])->toBe('today')
        ->and($tiktok['top_accounts'])->toBe([]);
});

test('top accounts are listed only once the bucket reaches the today threshold', function () {
    $bucket = NetworkCallBucket::factory()->create(['network' => 'tiktok', 'calls' => 50]);
    AccountNetworkCall::factory()->create(['network' => 'tiktok', 'calls' => 50]);

    $below = collect($this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.network-limits.show'))
        ->assertOk()
        ->json('networks'))->firstWhere('network', 'tiktok');

    expect($below['top_accounts'])->toBe([]);

    $bucket->update(['calls' => 70]);

    $above = collect($this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.network-limits.show'))
        ->assertOk()
        ->json('networks'))->firstWhere('network', 'tiktok');

    expect($above['top_accounts'])->toHaveCount(1)
        ->and($above['top_accounts'][0]['calls'])->toBe(50)
        ->and($above['top_accounts'][0]['percent'])->toBe(50);
});

test('the accounts list is ordered by calls and paginated at the configured size', function () {
    config(['app.pagination.default' => 2]);

    AccountNetworkCall::factory()->create(['network' => 'tiktok', 'calls' => 5]);
    AccountNetworkCall::factory()->create(['network' => 'tiktok', 'calls' => 9]);
    AccountNetworkCall::factory()->create(['network' => 'tiktok', 'calls' => 1]);

    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.network-limits.accounts', ['network' => 'tiktok', 'per_page' => 100]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.calls'))->toBe(9)
        ->and($response->json('data.1.calls'))->toBe(5)
        ->and($response->json('meta.per_page'))->toBe(2)
        ->and($response->json('meta.total'))->toBe(3);
});

test('an unknown network answers 404', function () {
    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.network-limits.accounts', ['network' => 'facebook']))
        ->assertNotFound();
});

test('the site token answers 404', function () {
    $this->withHeaders(['Authorization' => 'Bearer test-site-token'])
        ->getJson(route('internal.network-limits.accounts', ['network' => 'tiktok']))
        ->assertNotFound();
});

test('the thresholds must keep today below urgent', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.network-limits.thresholds.update'), [
            'today_percent' => 90,
            'urgent_percent' => 80,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('today_percent');

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.network-limits.thresholds.update'), [
            'today_percent' => 95,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('today_percent');
});

test('a PATCH persists the thresholds and audits before and after', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.network-limits.thresholds.update'), ['today_percent' => 60])
        ->assertOk()
        ->assertJsonPath('today_percent', 60)
        ->assertJsonPath('urgent_percent', 90)
        ->assertJsonPath('account_digest_percent', 10);

    $this->assertDatabaseHas('network_limit_settings', [
        'id' => NetworkLimitSetting::SINGLETON_ID,
        'today_percent' => 60,
        'urgent_percent' => 90,
        'account_digest_percent' => 10,
    ]);

    $entry = AuditLog::where('action', 'network-limit-thresholds.update')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('network_limit_settings')
        ->and($entry->target_id)->toBe((string) NetworkLimitSetting::SINGLETON_ID)
        ->and($entry->before)->toBe(['today_percent' => 70, 'urgent_percent' => 90, 'account_digest_percent' => 10])
        ->and($entry->after)->toBe(['today_percent' => 60, 'urgent_percent' => 90, 'account_digest_percent' => 10]);
});

test('a write without the admin actor header answers 422', function () {
    $this->withHeaders(['Authorization' => 'Bearer test-admin-token'])
        ->patchJson(route('internal.network-limits.thresholds.update'), ['today_percent' => 60])
        ->assertUnprocessable();
});
