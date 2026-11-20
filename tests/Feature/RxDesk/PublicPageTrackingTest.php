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
