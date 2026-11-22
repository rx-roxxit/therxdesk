<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Operator;

beforeEach(function () {
    Operator::forgetCurrent();
    Operator::query()->delete();
});

afterEach(fn () => Operator::forgetCurrent());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function operatorCommandOptions(array $overrides = []): array
{
    return [
        'action' => 'set',
        '--legal-name-th' => 'บริษัท ทดสอบ จำกัด',
        '--legal-name-latin' => 'Test Operator Co., Ltd.',
        '--address-th' => '123 ถนนทดสอบ กรุงเทพมหานคร 10110',
        '--address-latin' => '123 Test Road, Bangkok 10110',
        '--tax-id' => '1234567890123',
        '--contact-email' => 'admin@example.com',
        ...$overrides,
    ];
}

test('set creates the first Operator and records an audit row as cli', function () {
    $this->artisan('rxdesk:operator', operatorCommandOptions())->assertSuccessful();

    $operator = Operator::query()->current()->sole();
    $entry = AuditLog::query()->where('action', 'operator.create')->sole();

    expect($operator->legal_name_latin)->toBe('Test Operator Co., Ltd.')
        ->and($operator->tax_id)->toBe('1234567890123')
        ->and($entry->actor)->toBe('cli')
        ->and($entry->target_id)->toBe($operator->id)
        ->and($entry->after['legal_name_latin'])->toBe('Test Operator Co., Ltd.');
});

test('show prints the current Operator', function () {
    Operator::factory()->create(['legal_name_latin' => 'Test Operator Co., Ltd.']);

    Operator::forgetCurrent();

    $this->artisan('rxdesk:operator', ['action' => 'show'])
        ->expectsOutputToContain('Test Operator Co., Ltd.')
        ->assertSuccessful();
});

test('set updates only the options it was given and records the before and after values', function () {
    $operator = Operator::factory()->create([
        'address_latin' => 'Old address',
        'vat_id' => 'VAT-1',
        'phone' => '555-0100',
    ]);

    Operator::forgetCurrent();

    $this->artisan('rxdesk:operator', [
        'action' => 'set',
        '--address-latin' => 'New address',
    ])->assertSuccessful();

    $operator->refresh();

    expect($operator->address_latin)->toBe('New address')
        ->and($operator->vat_id)->toBe('VAT-1')
        ->and($operator->phone)->toBe('555-0100');

    $entry = AuditLog::query()->where('action', 'operator.update')->sole();

    expect($entry->actor)->toBe('cli')
        ->and($entry->before['address_latin'])->toBe('Old address')
        ->and($entry->after['address_latin'])->toBe('New address');
});

test('an empty VAT id or phone clears it and omitting it keeps it', function () {
    $operator = Operator::factory()->create([
        'vat_id' => 'VAT-1',
        'phone' => '555-0100',
    ]);

    Operator::forgetCurrent();

    $this->artisan('rxdesk:operator', [
        'action' => 'set',
        '--vat-id' => '',
        '--phone' => '',
    ])->assertSuccessful();

    $operator->refresh();

    expect($operator->vat_id)->toBeNull()
        ->and($operator->phone)->toBeNull();
});

test('a future effective_from fails and writes nothing', function () {
    $this->artisan('rxdesk:operator', operatorCommandOptions([
        '--effective-from' => now()->addDay()->toIso8601String(),
    ]))->assertFailed();

    expect(Operator::query()->count())->toBe(0);
});

test('a write through the CLI clears the cached current row', function () {
    expect(Operator::current())->toBeNull();

    $this->artisan('rxdesk:operator', operatorCommandOptions())->assertSuccessful();

    expect(Operator::current()?->legal_name_latin)->toBe('Test Operator Co., Ltd.');
});

test('show says when no Operator is recorded', function () {
    $this->artisan('rxdesk:operator', ['action' => 'show'])
        ->expectsOutputToContain('No Operator is recorded.')
        ->assertSuccessful();
});

test('show prints the history when an earlier Operator exists', function () {
    Operator::factory()->create([
        'legal_name_latin' => 'Previous Operator Co., Ltd.',
        'effective_from' => now()->subYear(),
        'ended_at' => now()->subMonth(),
    ]);
    Operator::factory()->create(['legal_name_latin' => 'Current Operator Co., Ltd.']);

    Operator::forgetCurrent();

    $this->artisan('rxdesk:operator', ['action' => 'show'])
        ->expectsOutputToContain('Current Operator Co., Ltd.')
        ->expectsOutputToContain('Previous Operator Co., Ltd.')
        ->assertSuccessful();
});
