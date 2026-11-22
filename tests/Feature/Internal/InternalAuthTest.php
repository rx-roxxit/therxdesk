<?php

declare(strict_types=1);

beforeEach(function () {
    config([
        'trypost.self_hosted' => false,
        'rxdesk.internal.admin_token' => 'test-admin-token',
        'rxdesk.internal.site_token' => 'test-site-token',
    ]);

    $this->adminHeaders = [
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'admin@example.com',
    ];
    $this->siteHeaders = ['Authorization' => 'Bearer test-site-token'];
});

test('an internal route answers 404 without a token', function (string $route) {
    $this->getJson(route($route))->assertNotFound();
})->with([
    'internal.site',
    'internal.accounts.index',
    'internal.audit-log.index',
    'internal.plans.index',
    'internal.offers.index',
    'internal.catalog-settings.show',
]);

test('an internal route answers 404 with a wrong token', function (string $route) {
    $this->withHeaders(['Authorization' => 'Bearer wrong-token'])
        ->getJson(route($route))
        ->assertNotFound();
})->with([
    'internal.site',
    'internal.accounts.index',
    'internal.audit-log.index',
    'internal.plans.index',
    'internal.offers.index',
    'internal.catalog-settings.show',
]);

test('the internal API is off when no token is configured', function () {
    config([
        'rxdesk.internal.admin_token' => null,
        'rxdesk.internal.site_token' => null,
    ]);

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index'))
        ->assertNotFound();

    $this->withHeaders($this->siteHeaders)
        ->getJson(route('internal.site'))
        ->assertNotFound();
});

test('the site token reaches only the site endpoint', function () {
    $this->withHeaders($this->siteHeaders)
        ->getJson(route('internal.site'))
        ->assertOk();

    $this->withHeaders($this->siteHeaders)
        ->getJson(route('internal.accounts.index'))
        ->assertNotFound();

    $this->withHeaders($this->siteHeaders)
        ->getJson(route('internal.audit-log.index'))
        ->assertNotFound();

    $this->withHeaders($this->siteHeaders)
        ->getJson(route('internal.offers.index'))
        ->assertNotFound();
});

test('an admin request must name the acting System admin', function () {
    $this->withHeaders(['Authorization' => 'Bearer test-admin-token'])
        ->getJson(route('internal.accounts.index'))
        ->assertUnprocessable();

    $this->withHeaders([
        'Authorization' => 'Bearer test-admin-token',
        'X-Internal-Admin-Email' => 'not-an-email',
    ])->getJson(route('internal.accounts.index'))
        ->assertUnprocessable();
});

test('the admin token reaches the customer and audit endpoints', function () {
    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.accounts.index'))
        ->assertOk();

    $this->withHeaders($this->adminHeaders)
        ->getJson(route('internal.audit-log.index'))
        ->assertOk();
});

test('the admin token reads the site endpoint without the admin header', function () {
    $this->withHeaders(['Authorization' => 'Bearer test-admin-token'])
        ->getJson(route('internal.site'))
        ->assertOk();
});
