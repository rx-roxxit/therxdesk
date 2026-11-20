<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

test('the legal pages render inside the public shell', function (string $routeName, string $title) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('legal/Show')
            ->where('title', $title)
            ->where('site.supportEmail', 'support@therxdesk.com')
            ->where('site.statusUrl', null)
            ->where('site.sourceUrl', 'https://github.com/rx-roxxit/therxdesk')
            ->where('site.upstreamUrl', 'https://github.com/trypostit/trypost'));
})->with([
    'privacy' => ['legal.privacy', 'Privacy Policy'],
    'terms' => ['legal.terms', 'Terms of Service'],
]);

test('the footer links come from the configuration', function () {
    config([
        'trypost.site.support_email' => 'hello@example.test',
        'trypost.site.status_url' => 'https://status.example.test',
        'trypost.site.source_url' => 'https://example.test/source',
        'trypost.site.upstream_url' => 'https://example.test/upstream',
    ]);

    $this->get(route('app.home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('site.supportEmail', 'hello@example.test')
            ->where('site.statusUrl', 'https://status.example.test')
            ->where('site.sourceUrl', 'https://example.test/source')
            ->where('site.upstreamUrl', 'https://example.test/upstream'));
});

test('there is no pricing page before the selling site ships', function () {
    // Nothing names this path in the app: the point is that no route (and so
    // no route name) exists until the v3.0.0 selling pages ship.
    $this->get('/pricing')->assertNotFound();
});
