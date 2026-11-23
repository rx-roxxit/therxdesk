<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\AvailabilitySwitch;

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

test('the list carries every switchable network except X and its state', function () {
    $response = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.availability.index'))
        ->assertOk();

    $switches = $response->json('switches');
    $values = array_column($switches, 'value');

    expect($switches)->toHaveCount(22)
        ->and($values)->toBe([
            'linkedin',
            'linkedin-page',
            'tiktok',
            'youtube',
            'facebook',
            'instagram',
            'instagram-facebook',
            'threads',
            'pinterest',
            'bluesky',
            'mastodon',
            'telegram',
            'discord',
            'google_business',
            'ai',
            'support_chat',
            'drive_backup',
            'drive_picker',
            'canva',
            'google_photos',
            'unsplash',
            'new_signups',
        ]);

    $linkedin = collect($switches)->firstWhere('value', 'linkedin');

    expect($linkedin)->toMatchArray([
        'key' => 'platform:linkedin',
        'kind' => 'platform',
        'label' => 'LinkedIn',
        'installed' => true,
        'switched_on' => true,
        'effective' => true,
    ]);
});

test('the list carries the services and signups with their default state', function () {
    $switches = $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.availability.index'))
        ->assertOk()
        ->json('switches');

    $ai = collect($switches)->firstWhere('value', 'ai');
    $chat = collect($switches)->firstWhere('value', 'support_chat');
    $signups = collect($switches)->firstWhere('value', 'new_signups');

    expect($ai)->toMatchArray([
        'key' => 'service:ai',
        'kind' => 'service',
        'label' => 'AI',
        'installed' => false,
        'switched_on' => true,
        'effective' => false,
    ])->and($chat)->toMatchArray([
        'key' => 'service:support_chat',
        'kind' => 'service',
        'label' => 'Support chat',
        'installed' => false,
        'switched_on' => false,
        'effective' => false,
    ])->and($signups)->toMatchArray([
        'key' => 'new_signups',
        'kind' => 'signups',
        'label' => 'New signups',
        'installed' => true,
        'switched_on' => false,
        'effective' => false,
    ]);
});

test('a PATCH turns AI off and on and audits both with the admin email', function () {
    config(['ai.default' => 'anthropic', 'ai.providers.anthropic.key' => 'test-key']);

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.availability.update', ['key' => 'service:ai']), ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('kind', 'service')
        ->assertJsonPath('value', 'ai')
        ->assertJsonPath('installed', true)
        ->assertJsonPath('switched_on', false)
        ->assertJsonPath('effective', false);

    $row = AvailabilitySwitch::where('key', 'service:ai')->sole();

    expect($row->enabled)->toBeFalse();

    $disabled = AuditLog::where('action', 'availability.disable')->sole();

    expect($disabled->actor)->toBe('admin@example.com')
        ->and($disabled->target_type)->toBe('availability_switches')
        ->and($disabled->target_id)->toBe($row->id)
        ->and($disabled->before)->toEqual(['enabled' => true])
        ->and($disabled->after)->toEqual(['enabled' => false]);

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.availability.update', ['key' => 'service:ai']), ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('switched_on', true)
        ->assertJsonPath('effective', true);

    $enabled = AuditLog::where('action', 'availability.enable')->sole();

    expect($enabled->actor)->toBe('admin@example.com')
        ->and($enabled->target_id)->toBe($row->id)
        ->and($enabled->before)->toEqual(['enabled' => false])
        ->and($enabled->after)->toEqual(['enabled' => true]);
});

test('a PATCH turns new signups on and audits it', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.availability.update', ['key' => 'new_signups']), ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('kind', 'signups')
        ->assertJsonPath('switched_on', true)
        ->assertJsonPath('effective', true);

    $row = AvailabilitySwitch::where('key', 'new_signups')->sole();

    expect($row->enabled)->toBeTrue();

    $enabled = AuditLog::where('action', 'availability.enable')->sole();

    expect($enabled->actor)->toBe('admin@example.com')
        ->and($enabled->target_id)->toBe($row->id)
        ->and($enabled->before)->toEqual(['enabled' => false])
        ->and($enabled->after)->toEqual(['enabled' => true]);
});

test('a service the server is not set up for cannot be switched on', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.availability.update', ['key' => 'service:drive_picker']), ['enabled' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');

    expect(AvailabilitySwitch::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('an unknown service key answers 404 and writes nothing', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.availability.update', ['key' => 'service:nope']), ['enabled' => true])
        ->assertNotFound();

    expect(AvailabilitySwitch::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('a PATCH turns a network off and on and audits both with the admin email', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.availability.update', ['key' => 'platform:linkedin']), ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('switched_on', false)
        ->assertJsonPath('effective', false);

    $row = AvailabilitySwitch::where('key', 'platform:linkedin')->sole();

    expect($row->enabled)->toBeFalse();

    $disabled = AuditLog::where('action', 'availability.disable')->sole();

    expect($disabled->actor)->toBe('admin@example.com')
        ->and($disabled->target_type)->toBe('availability_switches')
        ->and($disabled->target_id)->toBe($row->id)
        ->and($disabled->before)->toEqual(['enabled' => true])
        ->and($disabled->after)->toEqual(['enabled' => false]);

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.availability.update', ['key' => 'platform:linkedin']), ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('switched_on', true)
        ->assertJsonPath('effective', true);

    $enabled = AuditLog::where('action', 'availability.enable')->sole();

    expect($enabled->actor)->toBe('admin@example.com')
        ->and($enabled->target_id)->toBe($row->id)
        ->and($enabled->before)->toEqual(['enabled' => false])
        ->and($enabled->after)->toEqual(['enabled' => true]);
});

test('a PATCH that changes nothing still records its audit row', function () {
    $headers = $this->adminHeaders;

    $this->withHeaders($headers)
        ->patchJson(route('internal.availability.update', ['key' => 'platform:linkedin']), ['enabled' => true])
        ->assertOk();

    $this->withHeaders($headers)
        ->patchJson(route('internal.availability.update', ['key' => 'platform:linkedin']), ['enabled' => true])
        ->assertOk();

    expect(AuditLog::where('action', 'availability.enable')->count())->toBe(2);
});

test('a network the server has no credentials for cannot be switched on', function () {
    config(['services.linkedin.client_secret' => null]);

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.availability.update', ['key' => 'platform:linkedin']), ['enabled' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');

    expect(AvailabilitySwitch::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('an unknown key answers 404 and writes nothing', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.availability.update', ['key' => 'platform:nope']), ['enabled' => true])
        ->assertNotFound();

    expect(AvailabilitySwitch::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('the site token answers 404 on both endpoints', function () {
    $headers = ['Authorization' => 'Bearer test-site-token'];

    $this->withHeaders($headers)
        ->getJson(route('internal.availability.index'))
        ->assertNotFound();

    $this->withHeaders($headers)
        ->patchJson(route('internal.availability.update', ['key' => 'platform:linkedin']), ['enabled' => false])
        ->assertNotFound();
});

test('enabled is required and must be a boolean', function () {
    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.availability.update', ['key' => 'platform:linkedin']), ['enabled' => 'yes'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');
});
