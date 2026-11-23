<?php

declare(strict_types=1);

use App\Enums\Availability\ServiceSwitch;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Availability\Availability;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

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

    $this->standard = Plan::where('slug', 'standard')->firstOrFail();
});

$create = function (array $payload = []): TestResponse {
    return test()->withHeaders(test()->adminHeaders)
        ->postJson(route('internal.accounts.store'), [
            'email' => 'reviewer@example.com',
            'password' => 'Str0ng-Passw0rd!2026',
            'plan_id' => test()->standard->id,
            ...$payload,
        ]);
};

$reviewAccess = function (Account $account, bool $enabled): TestResponse {
    return test()->withHeaders(test()->adminHeaders)
        ->patchJson(route('internal.accounts.review-access.update', $account->id), ['enabled' => $enabled]);
};

test('creating a reviewer account returns the Account, its owner and the Complimentary state', function () use ($create) {
    $response = $create();

    $response->assertCreated()
        ->assertJsonPath('owner.email', 'reviewer@example.com')
        ->assertJsonPath('plan.slug', 'standard')
        ->assertJsonPath('complimentary.in_force', true)
        ->assertJsonPath('review_access', false);

    $account = Account::query()->whereKey($response->json('id'))->sole();

    expect($account->owner->email)->toBe('reviewer@example.com')
        ->and($account->owner->name)->toBe('reviewer')
        ->and($account->owner->email_verified_at)->not->toBeNull()
        ->and($account->plan_id)->toBe($this->standard->id)
        ->and($account->complimentary_since)->not->toBeNull()
        ->and($account->complimentary_until)->toBeNull()
        ->and($account->hasAppAccess())->toBeTrue()
        ->and($account->hasReviewAccess())->toBeFalse();
});

test('a default Workspace is created for the new Owner', function () use ($create) {
    $accountId = $create()->assertCreated()->json('id');

    $workspaces = Workspace::query()->where('account_id', $accountId)->get();

    expect($workspaces)->toHaveCount(1)
        ->and($workspaces->first()->name)->toBe("reviewer's Workspace");
});

test('the created Account signs in with the password it was given', function () use ($create) {
    $create()->assertCreated();

    $this->post(route('login.store'), [
        'email' => 'reviewer@example.com',
        'password' => 'Str0ng-Passw0rd!2026',
        'locale' => 'en',
    ])->assertRedirect();

    $this->assertAuthenticated();
    expect(auth()->user()?->email)->toBe('reviewer@example.com');
});

test('creating a reviewer account with review access on makes it a reviewer', function () use ($create) {
    $response = $create(['review_access' => true]);

    $response->assertCreated()->assertJsonPath('review_access', true);

    expect(Account::query()->whereKey($response->json('id'))->sole()->hasReviewAccess())->toBeTrue();
});

test('creating a reviewer account sends no email', function () use ($create) {
    Mail::fake();

    $create()->assertCreated();

    Mail::assertNothingSent();
});

test('creating a reviewer account records an audit entry that holds no password', function () use ($create) {
    $accountId = $create(['review_access' => true])->assertCreated()->json('id');

    $entry = AuditLog::query()->where('action', 'account.create')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('accounts')
        ->and($entry->target_id)->toBe($accountId)
        ->and($entry->after['email'])->toBe('reviewer@example.com')
        ->and($entry->after['plan_id'])->toBe($this->standard->id)
        ->and($entry->after['review_access'])->toBeTrue()
        ->and($entry->after)->not->toHaveKey('password')
        ->and(json_encode($entry->after))->not->toContain('Str0ng-Passw0rd!2026');
});

test('an archived Plan can be given to a reviewer account', function () use ($create) {
    $archived = Plan::factory()->archived()->create(['slug' => 'legacy', 'name' => 'Legacy']);

    $response = $create(['plan_id' => $archived->id])->assertCreated()->assertJsonPath('plan.slug', 'legacy');

    expect(Account::query()->whereKey($response->json('id'))->sole()->plan_id)->toBe($archived->id);
});

$refusal = fn (array $payload): TestResponse => test()->withHeaders(test()->adminHeaders)
    ->postJson(route('internal.accounts.store'), $payload);

test('an email used by any User is refused with 422 and nothing is created', function () use ($refusal) {
    User::factory()->create(['email' => 'taken@example.com']);

    $users = User::query()->count();
    $accounts = Account::query()->count();

    $refusal([
        'email' => 'taken@example.com',
        'password' => 'Str0ng-Passw0rd!2026',
        'plan_id' => $this->standard->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(User::query()->count())->toBe($users)
        ->and(Account::query()->count())->toBe($accounts)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('an unknown Plan is refused with 422 and nothing is created', function () use ($refusal) {
    $users = User::query()->count();
    $accounts = Account::query()->count();

    $refusal([
        'email' => 'reviewer@example.com',
        'password' => 'Str0ng-Passw0rd!2026',
        'plan_id' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonValidationErrors('plan_id');

    expect(User::query()->count())->toBe($users)
        ->and(Account::query()->count())->toBe($accounts)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('a password that fails the registration rules is refused with 422 and nothing is created', function () use ($refusal) {
    $users = User::query()->count();
    $accounts = Account::query()->count();

    $refusal([
        'email' => 'reviewer@example.com',
        'password' => 'short',
        'plan_id' => $this->standard->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    expect(User::query()->count())->toBe($users)
        ->and(Account::query()->count())->toBe($accounts)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('the review-access endpoint turns Review access on and audits it', function () use ($reviewAccess) {
    $account = Account::factory()->create(['review_access' => false]);

    $reviewAccess($account, true)
        ->assertOk()
        ->assertJsonPath('id', $account->id)
        ->assertJsonPath('review_access', true);

    expect($account->fresh()->hasReviewAccess())->toBeTrue();

    $entry = AuditLog::query()->where('action', 'account.review_access.enable')->sole();

    expect($entry->actor)->toBe('admin@example.com')
        ->and($entry->target_type)->toBe('accounts')
        ->and($entry->target_id)->toBe($account->id)
        ->and($entry->before)->toEqual(['review_access' => false])
        ->and($entry->after)->toEqual(['review_access' => true]);
});

test('the review-access endpoint turns Review access off and audits it', function () use ($reviewAccess) {
    $account = Account::factory()->create(['review_access' => true]);

    $reviewAccess($account, false)
        ->assertOk()
        ->assertJsonPath('review_access', false);

    expect($account->fresh()->hasReviewAccess())->toBeFalse();

    $entry = AuditLog::query()->where('action', 'account.review_access.disable')->sole();

    expect($entry->before)->toEqual(['review_access' => true])
        ->and($entry->after)->toEqual(['review_access' => false]);
});

test('the review-access switch works whether new signups are open or closed', function (bool $open) use ($reviewAccess) {
    app(Availability::class)->set(ServiceSwitch::NewSignups->value, $open);

    $account = Account::factory()->create(['review_access' => false]);

    $reviewAccess($account, true)->assertOk()->assertJsonPath('review_access', true);
})->with([true, false]);

test('the review-access endpoint requires a boolean enabled', function () {
    $account = Account::factory()->create(['review_access' => false]);

    $this->withHeaders($this->adminHeaders)
        ->patchJson(route('internal.accounts.review-access.update', $account->id), ['enabled' => 'yes'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');

    expect($account->fresh()->hasReviewAccess())->toBeFalse()
        ->and(AuditLog::query()->count())->toBe(0);
});
