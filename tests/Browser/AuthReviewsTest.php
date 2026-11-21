<?php

declare(strict_types=1);

test('the auth screens render the form column without the removed reviews panel', function (string $route) {
    config(['trypost.self_hosted' => false]);

    $page = visit(route($route, $route === 'password.reset' ? ['token' => 'preview-token'] : []))
        ->resize(1280, 900);

    waitFor($page, '@auth-logo');

    $page->assertVisible('@auth-content')
        ->assertVisible('@auth-logo')
        ->assertMissing('@auth-reviews')
        ->assertNoJavaScriptErrors();
})->with(['login', 'register', 'password.request', 'password.reset']);

test('the auth column keeps a 16px gutter without overflow on phones', function (string $route) {
    config(['trypost.self_hosted' => false]);

    $page = visit(route($route))->resize(390, 844);
    $page->assertVisible('@auth-logo');

    $layout = $page->script(<<<'JS'
        (() => {
            const email = document.querySelector('#email').getBoundingClientRect();
            return {
                overflow: document.documentElement.scrollWidth > window.innerWidth,
                left: Math.round(email.left),
                right: Math.round(window.innerWidth - email.right),
                height: Math.round(email.height),
            };
        })()
    JS);

    expect($layout)->toBe(['overflow' => false, 'left' => 16, 'right' => 16, 'height' => 32]);
})->with(['login']);
