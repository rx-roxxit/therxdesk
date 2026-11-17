<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('a guest can read the privacy policy and the terms', function (string $routeName, string $title) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('legal/Show')
            ->where('title', $title)
            ->where('body_html', fn (string $html): bool => str_contains($html, 'admin@therxdesk.com')));
})->with([
    'privacy' => ['legal.privacy', 'Privacy Policy'],
    'terms' => ['legal.terms', 'Terms of Service'],
]);

test('a signed-in user can read them too', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('legal.privacy'))
        ->assertOk();
});

test('the privacy policy carries what Google, YouTube and Meta ask for', function () {
    $this->get(route('legal.privacy'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('body_html', fn (string $html): bool => str_contains($html, 'Limited Use')
                && str_contains($html, 'https://www.youtube.com/t/terms')
                && str_contains($html, 'https://policies.google.com/privacy')
                && str_contains($html, 'https://security.google.com/settings/security/permissions')
                && str_contains($html, 'id="data-deletion"')));
});

test('the page title is not repeated in the body', function () {
    $this->get(route('legal.terms'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('body_html', fn (string $html): bool => ! str_contains($html, '<h1')));
});
