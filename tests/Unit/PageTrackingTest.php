<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs `tests/fixtures/page-tracking-harness.ts` over
 * `resources/js/lib/tracking.ts`: each visit is the pages a tab opens in
 * order, and the result is the tracking calls each visit made.
 *
 * @param  list<list<array{url: string, publicPage?: bool}>>  $visits
 * @return list<list<string>>
 */
function runPageTrackingHarness(array $visits): array
{
    $node = (new ExecutableFinder)->find('node');

    if ($node === null) {
        test()->markTestSkipped('node is unavailable');
    }

    $process = (new Process([
        $node,
        base_path('tests/fixtures/page-tracking-harness.ts'),
        resource_path('js/lib/tracking.ts'),
        json_encode($visits),
    ]))->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

test('an app page starts PostHog and the data layer once, and identifies and captures every page', function () {
    expect(runPageTrackingHarness([[
        ['url' => '/calendar', 'publicPage' => false],
        ['url' => '/posts', 'publicPage' => false],
    ]]))->toBe([[
        'dataLayer /calendar',
        'posthog',
        'identify /calendar',
        'pageview',
        'identify /posts',
        'pageview',
    ]]);
});

test('a public page starts no tracking and captures nothing', function () {
    expect(runPageTrackingHarness([
        [['url' => '/', 'publicPage' => true]],
        [['url' => '/privacy', 'publicPage' => true], ['url' => '/terms', 'publicPage' => true]],
    ]))->toBe([[], []]);
});

test('a visit that starts on a public page starts tracking on the first app page it reaches', function () {
    expect(runPageTrackingHarness([[
        ['url' => '/', 'publicPage' => true],
        ['url' => '/login', 'publicPage' => false],
        ['url' => '/terms', 'publicPage' => true],
        ['url' => '/calendar', 'publicPage' => false],
    ]]))->toBe([[
        'dataLayer /login',
        'posthog',
        'identify /login',
        'pageview',
        'identify /calendar',
        'pageview',
    ]]);
});

test('a page that does not say it is public is tracked', function () {
    expect(runPageTrackingHarness([[['url' => '/calendar']]]))->toBe([[
        'dataLayer /calendar',
        'posthog',
        'identify /calendar',
        'pageview',
    ]]);
});
