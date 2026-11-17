<?php

declare(strict_types=1);

use App\Models\SignupCode;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('create issues an unused code with an optional note', function () {
    expect(Artisan::call('rxdesk:code', ['action' => 'create', '--note' => 'For Ann']))->toBe(0);

    $signupCode = SignupCode::sole();

    expect($signupCode->code)->toMatch('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/')
        ->and($signupCode->note)->toBe('For Ann')
        ->and($signupCode->used_at)->toBeNull()
        ->and($signupCode->revoked_at)->toBeNull()
        ->and(Artisan::output())->toContain($signupCode->code);
});

test('create works without a note and never repeats a code', function () {
    Artisan::call('rxdesk:code', ['action' => 'create']);
    Artisan::call('rxdesk:code', ['action' => 'create']);

    $codes = SignupCode::pluck('code');

    expect($codes)->toHaveCount(2)
        ->and($codes->unique())->toHaveCount(2)
        ->and(SignupCode::whereNotNull('note')->exists())->toBeFalse();
});

test('list shows unused, used (by whom and when) and revoked codes', function () {
    $usedOn = now('UTC')->subDays(3)->startOfMinute();
    $revokedOn = now('UTC')->subDay()->startOfMinute();
    $owner = User::factory()->create(['name' => 'Ann', 'email' => 'ann@example.com']);

    SignupCode::factory()->create(['code' => 'AAAA-AAAA-AAAA', 'note' => 'Spare']);
    SignupCode::factory()->usedBy($owner, $usedOn)->create(['code' => 'BBBB-BBBB-BBBB']);
    SignupCode::factory()->revoked($revokedOn)->create(['code' => 'CCCC-CCCC-CCCC']);

    expect(Artisan::call('rxdesk:code', ['action' => 'list']))->toBe(0);

    expect(Artisan::output())
        ->toContain('AAAA-AAAA-AAAA')
        ->toContain('Spare')
        ->toContain('Unused')
        ->toContain('BBBB-BBBB-BBBB')
        ->toContain("Used by Ann <ann@example.com> on {$usedOn->format('Y-m-d H:i')} UTC")
        ->toContain('CCCC-CCCC-CCCC')
        ->toContain("Revoked on {$revokedOn->format('Y-m-d H:i')} UTC");
});

test('list says when there are no codes yet', function () {
    expect(Artisan::call('rxdesk:code', ['action' => 'list']))->toBe(0)
        ->and(Artisan::output())->toContain('No signup codes yet.');
});

test('revoke cancels an unused code, typed in any case', function () {
    $signupCode = SignupCode::factory()->create(['code' => 'ABCD-EFGH-JKMN']);

    expect(Artisan::call('rxdesk:code', ['action' => 'revoke', 'code' => ' abcd-efgh-jkmn ']))->toBe(0)
        ->and($signupCode->fresh()->revoked_at)->not->toBeNull();
});

test('revoke refuses a used, revoked or unknown code', function (Closure $makeCode, string $message) {
    $signupCode = $makeCode();

    expect(Artisan::call('rxdesk:code', ['action' => 'revoke', 'code' => 'ABCD-EFGH-JKMN']))->toBe(1)
        ->and(Artisan::output())->toContain($message);

    if ($signupCode !== null) {
        expect($signupCode->fresh()->only(['used_at', 'revoked_at']))->toEqual($signupCode->only(['used_at', 'revoked_at']));
    }
})->with([
    'used' => [fn () => SignupCode::factory()->usedBy(User::factory()->create())->create(['code' => 'ABCD-EFGH-JKMN']), 'ABCD-EFGH-JKMN was already used'],
    'revoked' => [fn () => SignupCode::factory()->revoked()->create(['code' => 'ABCD-EFGH-JKMN']), 'ABCD-EFGH-JKMN was already revoked'],
    'unknown' => [fn () => null, 'No signup code ABCD-EFGH-JKMN'],
]);

test('revoke needs a code', function () {
    expect(Artisan::call('rxdesk:code', ['action' => 'revoke']))->toBe(1)
        ->and(Artisan::output())->toContain('Pass the code to revoke');
});

test('an unknown action fails', function () {
    expect(Artisan::call('rxdesk:code', ['action' => 'delete']))->toBe(1)
        ->and(Artisan::output())->toContain('Unknown action "delete"');
});
