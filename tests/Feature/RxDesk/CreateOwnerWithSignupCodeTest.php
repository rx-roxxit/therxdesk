<?php

declare(strict_types=1);

use App\Actions\SignupCode\CreateOwnerWithSignupCode;
use App\Models\Account;
use App\Models\SignupCode;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @return array{name: string, email: string, password: string, locale: string}
 */
function ownerDetails(string $name): array
{
    return [
        'name' => $name,
        'email' => Str::lower("{$name}@example.com"),
        'password' => strongPassword(),
        'locale' => 'en',
    ];
}

test('of two signups that both passed validation with one code, only the first creates a user', function () {
    $signupCode = SignupCode::factory()->create();

    $ann = CreateOwnerWithSignupCode::execute($signupCode->code, ownerDetails('Ann'));
    $accountsAfterAnn = Account::count();
    $workspacesAfterAnn = Workspace::count();

    expect(fn () => CreateOwnerWithSignupCode::execute($signupCode->code, ownerDetails('Bob')))
        ->toThrow(ValidationException::class, 'This signup code was already used. Ask the admin for a new one.');

    expect(User::where('email', 'bob@example.com')->exists())->toBeFalse()
        ->and(Account::count())->toBe($accountsAfterAnn)
        ->and(Workspace::count())->toBe($workspacesAfterAnn)
        ->and($signupCode->fresh()->used_by_user_id)->toBe($ann->id);
});

test('a code revoked after the form was validated creates nothing', function () {
    $signupCode = SignupCode::factory()->revoked()->create();

    expect(fn () => CreateOwnerWithSignupCode::execute($signupCode->code, ownerDetails('Ann')))
        ->toThrow(ValidationException::class, 'This signup code was revoked. Ask the admin for a new one.');

    expect(User::where('email', 'ann@example.com')->exists())->toBeFalse()
        ->and($signupCode->fresh()->used_at)->toBeNull();
});

test('the code row is locked before the account is created, so a racing signup waits for it', function () {
    $signupCode = SignupCode::factory()->create();
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = Str::lower($query->sql);
    });

    CreateOwnerWithSignupCode::execute($signupCode->code, ownerDetails('Ann'));

    $lockAt = collect($queries)->search(fn (string $sql): bool => Str::contains($sql, 'signup_codes') && Str::contains($sql, 'for update'));
    $firstAccountInsert = collect($queries)->search(fn (string $sql): bool => Str::startsWith($sql, 'insert into') && Str::contains($sql, 'accounts'));

    expect($lockAt)->toBeInt()
        ->and($firstAccountInsert)->toBeInt()
        ->and($lockAt)->toBeLessThan($firstAccountInsert);
});
