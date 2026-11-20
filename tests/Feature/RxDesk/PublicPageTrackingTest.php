<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Cookie;

/** A signed-in member with a workspace, for a page of the app. */
function trackedAppUser(): User
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

dataset('public pages', [
    'landing' => ['app.home'],
    'privacy' => ['legal.privacy'],
    'terms' => ['legal.terms'],
]);

test('a public page is marked public and carries no tracker, even with GTM and PostHog configured', function (string $routeName) {
    config([
        'services.gtm.id' => 'GTM-TEST123',
        'services.posthog.enabled' => true,
        'services.posthog.api_key' => 'phc_test_key',
    ]);

    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('publicPage', true))
        ->assertDontSee('googletagmanager')
        ->assertDontSee('gtag')
        ->assertDontSee('posthog');
})->with('public pages');

test('a legal page read inside a session stays public', function () {
    config(['services.gtm.id' => 'GTM-TEST123']);

    $this->actingAs(trackedAppUser())
        ->get(route('legal.privacy'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('publicPage', true))
        ->assertDontSee('googletagmanager');
});

test('an app page keeps Google Tag Manager, its noscript include and its tracking', function () {
    config(['services.gtm.id' => 'GTM-TEST123']);

    $this->actingAs(trackedAppUser())
        ->get(route('app.settings.preferences'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('publicPage', false))
        ->assertSee('https://www.googletagmanager.com/gtm.js', escape: false)
        ->assertSee('https://www.googletagmanager.com/ns.html?id=GTM-TEST123', escape: false);
});

test('a public page sets only the session and XSRF cookies', function (string $routeName) {
    $response = $this->get(route($routeName))->assertOk();

    $names = Arr::map($response->headers->getCookies(), fn (Cookie $cookie): string => $cookie->getName());

    expect($names)->toEqualCanonicalizing(['XSRF-TOKEN', config('session.cookie')]);
})->with('public pages');

/** The asset version the page of this route was rendered with. */
function inertiaVersionOf(mixed $testCase, string $url): string
{
    return (string) data_get($testCase->get($url)->viewData('page'), 'version');
}

test('a visit from a public page into the app loads the app page in full, so its trackers start there', function () {
    $publicVersion = inertiaVersionOf($this, route('app.home'));

    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $publicVersion])
        ->get(route('login'))
        ->assertConflict()
        ->assertHeader('X-Inertia-Location', route('login'));
});

test('a visit from the app to a public page loads it in full, so no tracker started in the app runs there', function () {
    $user = trackedAppUser();
    $appVersion = inertiaVersionOf($this->actingAs($user), route('app.settings.preferences'));

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $appVersion])
        ->get(route('legal.terms'))
        ->assertConflict()
        ->assertHeader('X-Inertia-Location', route('legal.terms'));
});

test('a visit between public pages stays inside the page', function () {
    $publicVersion = inertiaVersionOf($this, route('app.home'));

    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $publicVersion])
        ->get(route('legal.privacy'))
        ->assertOk()
        ->assertJsonPath('component', 'legal/Show');
});
