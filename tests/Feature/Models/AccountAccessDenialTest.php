<?php

declare(strict_types=1);

use App\Enums\Account\AccessDenial;
use App\Models\Account;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
});

test('an active subscription has no access denial', function () {
    $account = Account::factory()->create();
    subscribeAccount($account);

    expect($account->hasAppAccess())->toBeTrue()
        ->and($account->isSuspended())->toBeFalse()
        ->and($account->accessDenial())->toBeNull();
});

test('an account without access is lapsed', function () {
    $account = Account::factory()->create(['trial_ends_at' => null]);

    expect($account->hasAppAccess())->toBeFalse()
        ->and($account->accessDenial())->toBe(AccessDenial::Lapsed);
});

test('a suspended account is suspended even with an active subscription', function () {
    $account = Account::factory()->create();
    subscribeAccount($account);
    $account->forceFill(['suspended_at' => now()])->save();

    expect($account->isSuspended())->toBeTrue()
        ->and($account->hasAppAccess())->toBeFalse()
        ->and($account->accessDenial())->toBe(AccessDenial::Suspended);
});

test('a suspension outranks self-hosted access', function () {
    config(['trypost.self_hosted' => true]);

    $account = Account::factory()->create();
    $account->forceFill(['suspended_at' => now()])->save();

    expect($account->hasAppAccess())->toBeFalse()
        ->and($account->accessDenial())->toBe(AccessDenial::Suspended);
});

test('the suspension reason never serialises with the account', function () {
    $account = Account::factory()->create([
        'suspended_at' => now(),
        'suspension_reason' => 'Internal note',
    ]);

    expect($account->toArray())->not->toHaveKey('suspension_reason')
        ->and($account->toJson())->not->toContain('Internal note');
});
