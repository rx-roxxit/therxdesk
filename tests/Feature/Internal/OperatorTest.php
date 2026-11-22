<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Operator;
use Illuminate\Support\Facades\Cache;

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

    Operator::forgetCurrent();
    Operator::query()->delete();
});

afterEach(fn () => Operator::forgetCurrent());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function operatorPayload(array $overrides = []): array
{
    return [
        'legal_name_th' => 'บริษัท ทดสอบ จำกัด',
        'legal_name_latin' => 'Test Operator Co., Ltd.',
        'address_th' => '123 ถนนทดสอบ กรุงเทพมหานคร 10110',
        'address_latin' => '123 Test Road, Bangkok 10110',
        'tax_id' => '1234567890123',
        'vat_id' => null,
        'contact_email' => 'admin@example.com',
        'phone' => null,
        ...$overrides,
    ];
}

test('an empty overview answers a null current row and an empty history', function () {
    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.operator.show'))
        ->assertOk()
        ->assertJsonPath('current', null)
        ->assertJsonPath('history', []);
});

test('creating the first Operator answers 201 with the overview and records an audit row', function () {
    $response = $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.operator.store'), operatorPayload())
        ->assertCreated()
        ->assertJsonPath('current.legal_name_latin', 'Test Operator Co., Ltd.')
        ->assertJsonPath('current.is_current', true)
        ->assertJsonPath('current.vat_id', null)
        ->assertJsonPath('history', []);

    $operator = Operator::query()->sole();
    $entry = AuditLog::query()->where('action', 'operator.create')->sole();

    expect($response->json('current.id'))->toBe($operator->id)
        ->and($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('operators')
        ->and($entry->target_id)->toBe($operator->id)
        ->and($entry->before)->toBeNull()
        ->and($entry->after['legal_name_latin'])->toBe('Test Operator Co., Ltd.');
});

test('the overview shows the current Operator', function () {
    $operator = Operator::factory()->create([
        'legal_name_latin' => 'Current Operator Co., Ltd.',
    ]);

    Operator::forgetCurrent();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.operator.show'))
        ->assertOk()
        ->assertJsonPath('current.id', $operator->id)
        ->assertJsonPath('current.legal_name_latin', 'Current Operator Co., Ltd.')
        ->assertJsonPath('history', []);
});

test('editing the current Operator records the before and after values', function () {
    $operator = Operator::factory()->create([
        'address_latin' => 'Old address',
    ]);

    Operator::forgetCurrent();

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.operator.update'), ['address_latin' => 'New address'])
        ->assertOk()
        ->assertJsonPath('current.address_latin', 'New address');

    expect($operator->refresh()->address_latin)->toBe('New address');

    $entry = AuditLog::query()->where('action', 'operator.update')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_id)->toBe($operator->id)
        ->and($entry->before['address_latin'])->toBe('Old address')
        ->and($entry->after['address_latin'])->toBe('New address');
});

test('a second create ends the previous row and keeps exactly one current row', function () {
    $first = $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.operator.store'), operatorPayload())
        ->assertCreated()
        ->json('current.id');

    $second = $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.operator.store'), operatorPayload([
            'legal_name_latin' => 'Replacement Operator Co., Ltd.',
        ]))
        ->assertCreated()
        ->json('current.id');

    expect(Operator::query()->count())->toBe(2)
        ->and(Operator::query()->current()->count())->toBe(1)
        ->and(Operator::query()->current()->sole()->id)->toBe($second);

    $ended = Operator::query()->findOrFail($first);
    $current = Operator::query()->findOrFail($second);

    expect($ended->ended_at?->equalTo($current->effective_from))->toBeTrue();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.operator.show'))
        ->assertOk()
        ->assertJsonPath('current.id', $second)
        ->assertJsonCount(1, 'history')
        ->assertJsonPath('history.0.id', $first)
        ->assertJsonPath('history.0.is_current', false);
});

test('a future effective_from is refused on create and stores nothing', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.operator.store'), operatorPayload([
            'effective_from' => now()->addDay()->toIso8601String(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('effective_from');

    expect(Operator::query()->count())->toBe(0);
});

test('a future effective_from is refused on update and changes nothing', function () {
    $operator = Operator::factory()->create(['address_latin' => 'Old address']);

    Operator::forgetCurrent();

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.operator.update'), [
            'address_latin' => 'New address',
            'effective_from' => now()->addDay()->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('effective_from');

    expect($operator->refresh()->address_latin)->toBe('Old address');
});

test('a tax id that is not 13 digits is refused', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.operator.store'), operatorPayload(['tax_id' => '123']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tax_id');

    expect(Operator::query()->count())->toBe(0);
});

test('a null VAT id is allowed', function () {
    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.operator.store'), operatorPayload(['vat_id' => null]))
        ->assertCreated()
        ->assertJsonPath('current.vat_id', null);
});

test('the site token answers 404 on every Operator endpoint', function () {
    $siteHeaders = ['Authorization' => 'Bearer test-site-token'];

    $this->withHeaders($siteHeaders)->getJson(route('internal.operator.show'))->assertNotFound();
    $this->withHeaders($siteHeaders)->postJson(route('internal.operator.store'), operatorPayload())->assertNotFound();
    $this->withHeaders($siteHeaders)->patchJson(route('internal.operator.update'), ['phone' => '555'])->assertNotFound();
});

test('a missing or invalid admin email answers 422', function () {
    $this->withHeaders(['Authorization' => 'Bearer test-admin-token'])
        ->getJson(route('internal.operator.show'))
        ->assertUnprocessable();

    $this->withHeaders([
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'not-an-email',
    ])->postJson(route('internal.operator.store'), operatorPayload())->assertUnprocessable();
});

test('the cached current row holds plain attributes, never a model', function () {
    Operator::factory()->create(['legal_name_latin' => 'Cached Operator Co., Ltd.']);

    expect(Operator::current()?->legal_name_latin)->toBe('Cached Operator Co., Ltd.');

    $cached = Cache::get(Operator::CURRENT_CACHE_KEY);

    expect($cached)->toBeArray()
        ->and($cached['current'])->toBeArray()
        ->and($cached['current']['legal_name_latin'])->toBe('Cached Operator Co., Ltd.');
});

test('a write through the API clears the cached current row', function () {
    expect(Operator::current())->toBeNull();

    $this->withHeaders($this->adminHeaders)
        ->postJson(route('internal.operator.store'), operatorPayload())
        ->assertCreated();

    expect(Operator::current()?->legal_name_latin)->toBe('Test Operator Co., Ltd.');
});
