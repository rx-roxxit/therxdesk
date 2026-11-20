<?php

declare(strict_types=1);

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Tests\TestCase;

test('robots.txt lets every crawler in and points at the sitemap on the configured app URL', function () {
    config(['app.url' => 'https://desk.example.test']);

    $response = $this->get(route('robots'))->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/plain')
        ->and($response->getContent())->toBe("User-agent: *\nDisallow:\n\nSitemap: https://desk.example.test/sitemap.xml\n");
});

test('the sitemap lists exactly the three public pages, with absolute URLs on the configured app URL', function () {
    config(['app.url' => 'https://desk.example.test']);

    $response = $this->get(route('sitemap'))->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('application/xml');

    $sitemap = new SimpleXMLElement((string) $response->getContent());
    $sitemap->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    expect(collect($sitemap->xpath('//s:url/s:loc'))->map(fn (SimpleXMLElement $loc): string => (string) $loc)->all())->toBe([
        'https://desk.example.test',
        'https://desk.example.test/terms',
        'https://desk.example.test/privacy',
    ]);
});

/** The `<head>` of a page as the server rendered it, before any JavaScript runs. */
function renderedHead(TestCase $testCase, string $routeName): Element
{
    $html = (string) $testCase->get(route($routeName))->assertOk()->getContent();

    return HTMLDocument::createFromString($html, LIBXML_NOERROR)->head;
}

/** The content (or href) of the one head tag the selector matches. */
function headValue(Element $head, string $selector, string $attribute = 'content'): ?string
{
    $matches = $head->querySelectorAll($selector);

    expect($matches)->toHaveCount(1, "{$selector} appears once");

    return $matches->item(0)->getAttribute($attribute);
}

dataset('public page meta', [
    'landing' => [
        'app.home',
        'The RX Desk',
        'Plan, write and schedule posts for every brand you manage, with a workspace, a calendar and approvals for each client.',
        'https://desk.example.test',
    ],
    'privacy' => [
        'legal.privacy',
        'Privacy Policy - The RX Desk',
        'What The RX Desk collects, why, what it reads from Google, YouTube and Meta, and how to delete your data.',
        'https://desk.example.test/privacy',
    ],
    'terms' => [
        'legal.terms',
        'Terms of Service - The RX Desk',
        'The terms for using The RX Desk: who can use it, your content, the networks you connect and what we are responsible for.',
        'https://desk.example.test/terms',
    ],
]);

test('a public page renders its title, description, canonical and Open Graph tags on the server', function (string $routeName, string $title, string $description, string $url) {
    config(['app.url' => 'https://desk.example.test', 'app.name' => 'The RX Desk']);

    $head = renderedHead($this, $routeName);

    expect($head->querySelector('title')->textContent)->toBe($title)
        ->and(headValue($head, 'meta[name="description"]'))->toBe($description)
        ->and(headValue($head, 'link[rel="canonical"]', 'href'))->toBe($url)
        ->and(headValue($head, 'meta[property="og:type"]'))->toBe('website')
        ->and(headValue($head, 'meta[property="og:site_name"]'))->toBe('The RX Desk')
        ->and(headValue($head, 'meta[property="og:title"]'))->toBe($title)
        ->and(headValue($head, 'meta[property="og:description"]'))->toBe($description)
        ->and(headValue($head, 'meta[property="og:url"]'))->toBe($url)
        ->and(headValue($head, 'meta[name="twitter:card"]'))->toBe('summary_large_image');
})->with('public page meta');

test('the Open Graph image is the 1200×630 build asset, by an absolute URL', function () {
    $random = Str::lower(Str::random(8));
    $buildDirectory = "og-test-build-{$random}";
    File::ensureDirectoryExists(public_path($buildDirectory));
    File::put(public_path("{$buildDirectory}/manifest.json"), json_encode([
        'resources/css/app.css' => ['file' => 'assets/app-a1.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
        'resources/js/app.ts' => ['file' => 'assets/app-b2.js', 'src' => 'resources/js/app.ts', 'isEntry' => true],
        'resources/js/assets/landing/og-image.jpg' => ['file' => 'assets/og-image-c3.jpg', 'src' => 'resources/js/assets/landing/og-image.jpg'],
    ]));

    $this->withVite();
    Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'))->useBuildDirectory($buildDirectory);

    try {
        $head = renderedHead($this, 'legal.terms');

        expect(headValue($head, 'meta[property="og:image"]'))->toBe(asset("{$buildDirectory}/assets/og-image-c3.jpg"))
            ->and(headValue($head, 'meta[property="og:image:width"]'))->toBe('1200')
            ->and(headValue($head, 'meta[property="og:image:height"]'))->toBe('630');
    } finally {
        File::deleteDirectory(public_path($buildDirectory));
    }
});

test('the committed Open Graph image is 1200×630', function () {
    expect(getimagesize(resource_path('js/assets/landing/og-image.jpg')))->toMatchArray([0 => 1200, 1 => 630]);
});

test('a visit inside the site carries the same meta, so the page head follows it without a reload', function () {
    config(['app.url' => 'https://desk.example.test']);

    $version = (string) data_get($this->get(route('app.home'))->viewData('page'), 'version');

    $this->get(route('legal.privacy'), ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->assertOk()
        ->assertJsonPath('props.meta.title', 'Privacy Policy')
        ->assertJsonPath('props.meta.canonical', 'https://desk.example.test/privacy');
});
