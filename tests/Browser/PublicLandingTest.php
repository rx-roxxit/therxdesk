<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;

test('a visitor signs in from the landing', function () {
    $page = visit(route('app.home'));

    waitFor($page, '@public-sign-in');
    $page->assertSee('Every brand you manage in one place.');

    $page->click('@public-sign-in');
    waitForPath($page, route('login', absolute: false));

    $page->assertNoJavaScriptErrors();
});

test('the landing switches language without reloading the page', function () {
    $page = visit(route('app.home'));

    waitFor($page, '@public-language-trigger');
    $page->script('window.__publicLandingMarker = 1');

    $page->click('@public-language-trigger');
    waitFor($page, '@public-language-option-ar');
    $page->click('@public-language-option-ar');

    waitForText($page, '@public-hero-title', 'كل العلامات');
    waitForCondition($page, "document.documentElement.dir === 'rtl'");

    expect($page->script('window.__publicLandingMarker'))->toBe(1);

    $page->assertNoJavaScriptErrors();
});

test('the footer links every public page is expected to carry are there', function () {
    $page = visit(route('legal.terms'));

    waitFor($page, '@public-footer-terms');
    $page->assertVisible('@public-footer-privacy')
        ->assertVisible('@public-footer-source')
        ->assertVisible('@public-footer-built-on')
        ->assertVisible('@public-footer-language-trigger')
        ->assertMissing('@public-footer-status')
        ->assertNoJavaScriptErrors();

    $page->assertSee('support@therxdesk.com');
});

test('the footer shows the status page only when one is configured', function () {
    config(['trypost.site.status_url' => 'https://status.example.test']);

    $page = visit(route('app.home'));

    waitFor($page, '@public-footer-status');
    $page->assertVisible('@public-footer-status')->assertNoJavaScriptErrors();
});

test('a signed-in visit to the home page lands on the calendar', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, membershipPivot('admin'));
    $user->update(['current_workspace_id' => $workspace->id]);
    $this->actingAs($user->fresh());

    $page = visit(route('app.home'));

    $calendarPath = json_encode(route('app.calendar', absolute: false));
    waitForCondition($page, "location.pathname.startsWith({$calendarPath})");

    $page->assertNoJavaScriptErrors();
});
