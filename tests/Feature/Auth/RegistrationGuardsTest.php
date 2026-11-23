<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.turnstile.site_key' => 'test-site-key',
        'rxdesk.turnstile.secret_key' => 'test-secret',
        'rxdesk.turnstile.verify_url' => 'https://turnstile.test/siteverify',
    ]);

    enableSignups();
});

/**
 * @return array<string, string>
 */
function turnstileRegistrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => strongPassword(),
        'country' => 'TH',
        'locale' => 'en',
        ...$overrides,
    ];
}

test('the register page sends the Turnstile site key when configured', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('turnstileSiteKey', 'test-site-key'));
});

test('the register page sends no Turnstile site key when none is configured', function () {
    config()->set('rxdesk.turnstile.site_key', null);

    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('turnstileSiteKey', null));
});

test('a registration without a Turnstile token is refused and creates no user', function () {
    Http::fake();

    $this->post(route('register.store'), turnstileRegistrationPayload())
        ->assertSessionHasErrors(['cf-turnstile-response' => __('rxdesk.turnstile.failed')]);

    Http::assertNotSent(fn (HttpClientRequest $request): bool => $request->url() === config('rxdesk.turnstile.verify_url'));
    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('a registration with a valid Turnstile token passes', function () {
    Http::fake([
        config('rxdesk.turnstile.verify_url') => Http::response(['success' => true]),
    ]);

    $this->post(route('register.store'), turnstileRegistrationPayload([
        'cf-turnstile-response' => 'a-valid-token',
    ]))->assertSessionHasNoErrors();

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();

    Http::assertSent(fn (HttpClientRequest $request): bool => $request->url() === config('rxdesk.turnstile.verify_url')
        && $request['secret'] === 'test-secret'
        && $request['response'] === 'a-valid-token'
        && $request['remoteip'] === '127.0.0.1');
});

test('a registration with an invalid Turnstile token is refused and creates no user', function () {
    Http::fake([
        config('rxdesk.turnstile.verify_url') => Http::response(['success' => false]),
    ]);

    $this->post(route('register.store'), turnstileRegistrationPayload([
        'cf-turnstile-response' => 'an-invalid-token',
    ]))->assertSessionHasErrors(['cf-turnstile-response' => __('rxdesk.turnstile.failed')]);

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('a Turnstile verification that cannot be reached refuses the registration', function () {
    Http::fake([
        config('rxdesk.turnstile.verify_url') => Http::response('', Response::HTTP_INTERNAL_SERVER_ERROR),
    ]);

    $this->post(route('register.store'), turnstileRegistrationPayload([
        'cf-turnstile-response' => 'a-token',
    ]))->assertSessionHasErrors(['cf-turnstile-response' => __('rxdesk.turnstile.failed')]);

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('no configured Turnstile secret skips the check', function () {
    config()->set('rxdesk.turnstile.secret_key', null);
    Http::fake();

    $this->post(route('register.store'), turnstileRegistrationPayload())
        ->assertSessionHasNoErrors();

    Http::assertNotSent(fn (HttpClientRequest $request): bool => $request->url() === config('rxdesk.turnstile.verify_url'));
    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});

test('the register throttle answers 429 after five POSTs from one IP', function () {
    config()->set('rxdesk.turnstile.secret_key', null);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('register.store'), ['name' => 'Incomplete'])
            ->assertSessionHasErrors();
    }

    $this->post(route('register.store'), ['name' => 'Incomplete'])
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
});

test('the register page stays reachable while the POST throttle is exhausted', function () {
    config()->set('rxdesk.turnstile.secret_key', null);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('register.store'), ['name' => 'Incomplete'])
            ->assertSessionHasErrors();
    }

    $this->post(route('register.store'), ['name' => 'Incomplete'])
        ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

    $this->get(route('register'))->assertOk();
});
