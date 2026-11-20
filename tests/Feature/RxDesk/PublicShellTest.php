<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\User\Locale;
use Illuminate\Support\Arr;
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

test('the landing lists the networks a visitor can connect now, one entry per network', function () {
    foreach (Platform::cases() as $platform) {
        config(["trypost.platforms.{$platform->value}.enabled" => false]);
    }

    config([
        'trypost.platforms.youtube.enabled' => true,
        'trypost.platforms.facebook.enabled' => true,
        'trypost.platforms.instagram-facebook.enabled' => true,
    ]);

    $this->get(route('app.home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/Landing')
            ->where('networks', [
                ['value' => 'facebook', 'label' => 'Facebook Page'],
                ['value' => 'instagram', 'label' => 'Instagram'],
                ['value' => 'youtube', 'label' => 'YouTube Shorts'],
            ]));

    config(['trypost.platforms.bluesky.enabled' => true, 'trypost.platforms.youtube.enabled' => false]);

    $this->get(route('app.home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('networks', [
                ['value' => 'bluesky', 'label' => 'Bluesky'],
                ['value' => 'facebook', 'label' => 'Facebook Page'],
                ['value' => 'instagram', 'label' => 'Instagram'],
            ]));
});

test('the pre-launch copy carries no price and no trial in any locale', function (Locale $locale) {
    $copy = Arr::dot(trans('rxdesk.public', [], $locale->value));

    expect($copy)->not->toBeEmpty();

    foreach ($copy as $key => $text) {
        expect($text)->not->toMatch('/[$€£¥]|\bUSD\b|\d+[.,]\d{2}/u', "rxdesk.public.{$key} ({$locale->value}) names a price");
    }

    foreach (Arr::dot(trans('rxdesk.public', [], Locale::DEFAULT->value)) as $key => $text) {
        expect($text)->not->toMatch('/\btrial\b|\bpric(e|es|ing)\b|\bfree\b|\bdiscount\b/i', "rxdesk.public.{$key} talks about selling");
    }
})->with(fn (): array => Locale::cases());

test('there is no pricing page before the selling site ships', function () {
    // Nothing names this path in the app: the point is that no route (and so
    // no route name) exists until the v3.0.0 selling pages ship.
    $this->get('/pricing')->assertNotFound();
});
