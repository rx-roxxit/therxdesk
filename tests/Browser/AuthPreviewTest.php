<?php

declare(strict_types=1);

function authPreviewBesideForm(mixed $page): bool
{
    return (bool) $page->script(<<<'JS'
        (() => {
            const form = document.querySelector('[data-testid="auth-content"]').getBoundingClientRect();
            const preview = document.querySelector('[data-testid="auth-preview"]').getBoundingClientRect();
            return Math.round(preview.left) >= Math.round(form.right);
        })()
    JS);
}

test('the auth screens show the app preview instead of reviews', function (string $route) {
    config(['trypost.self_hosted' => false]);

    $page = visit(route($route, $route === 'password.reset' ? ['token' => 'preview-token'] : []))
        ->resize(1280, 900);

    waitFor($page, '@auth-preview-image');

    $page->assertVisible('@auth-content')
        ->assertVisible('@auth-logo')
        ->assertVisible('@auth-preview')
        ->assertMissing('@auth-reviews')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelector(\'[data-testid="auth-preview-image"]\').naturalWidth'))->toBeGreaterThan(0);
})->with(['login', 'register', 'password.request', 'password.reset']);

test('the preview sits beside the form above 900px and stacks under it at 900px', function () {
    config(['trypost.self_hosted' => false]);

    $page = visit(route('login'))->resize(1280, 900);
    waitFor($page, '@auth-preview');

    expect(authPreviewBesideForm($page))->toBeTrue();

    $page->resize(900, 900);

    waitForState(
        $page,
        '@auth-preview',
        'element.getBoundingClientRect().top >= document.querySelector(\'[data-testid="auth-content"]\').getBoundingClientRect().bottom',
    );

    expect(authPreviewBesideForm($page))->toBeFalse();
});

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
