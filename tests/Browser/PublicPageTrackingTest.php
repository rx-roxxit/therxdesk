<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * The names of the cookies the page's scripts can read: a tracker's cookies
 * (PostHog's `ph_*`, GTM's `_ga`) would show here, the session cookie never
 * does (it is HTTP-only).
 */
function readableCookieNames(mixed $page): array
{
    return $page->script("document.cookie.split('; ').filter(Boolean).map((cookie) => cookie.split('=')[0])");
}

test('a public page starts no tracker', function (string $routeName, string $readyElement) {
    config(['services.gtm.id' => 'GTM-TEST123']);

    $page = visit(route($routeName));

    waitFor($page, $readyElement);
    letPageRun($page, 500);

    $html = Str::lower($page->content());

    expect($html)->not->toContain('posthog')
        ->and($html)->not->toContain('googletagmanager')
        ->and($html)->not->toContain('gtag');

    expect($page->script("typeof window.dataLayer === 'undefined'"))->toBeTrue();
    expect(readableCookieNames($page))->toBe(['XSRF-TOKEN']);
    expect(array_filter(localStorageKeys($page), fn (string $key): bool => Str::startsWith($key, 'ph_')))->toBeEmpty();

    $page->assertNoJavaScriptErrors();
})->with([
    'landing' => ['app.home', '@public-sign-in'],
    'privacy' => ['legal.privacy', '@public-footer-terms'],
    'terms' => ['legal.terms', '@public-footer-terms'],
]);

test('leaving the landing for the sign-in page starts the tracking there', function () {
    $page = visit(route('app.home'));

    waitFor($page, '@public-sign-in');
    expect($page->script("typeof window.dataLayer === 'undefined'"))->toBeTrue();

    $page->click('@public-sign-in');
    waitForPath($page, route('login', absolute: false));

    waitForCondition($page, "Array.isArray(window.dataLayer) && window.dataLayer.some((entry) => entry.app_context === 'app')");

    $page->assertNoJavaScriptErrors();
});

test('a signed-in app page still starts its tracking', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $this->actingAs($user->fresh());

    $page = visit(route('app.settings.preferences'));

    $userId = json_encode($user->id);
    waitForCondition($page, "Array.isArray(window.dataLayer) && window.dataLayer.some((entry) => entry.user_id === {$userId})");

    $page->assertNoJavaScriptErrors();
});
