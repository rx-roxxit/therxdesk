<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The faces the app serves from its own build, keyed by their
 * `@fontsource-variable` package: the family the package declares, the custom
 * property that names it, and the generic family its stack falls back to.
 */
const SELF_HOSTED_FACES = [
    'inter' => ['family' => 'Inter Variable', 'property' => '--font-sans', 'generic' => 'sans-serif'],
    'outfit' => ['family' => 'Outfit Variable', 'property' => '--font-heading', 'generic' => 'sans-serif'],
    'jetbrains-mono' => ['family' => 'JetBrains Mono Variable', 'property' => '--font-mono', 'generic' => 'monospace'],
    'roboto-slab' => ['family' => 'Roboto Slab Variable', 'property' => '--font-logo', 'generic' => 'serif'],
];

/**
 * Every `<property> => [stack, ...]` the stylesheet declares, whitespace
 * flattened: the light and dark blocks each declare one, and the `@theme
 * inline` mirrors (`var(--font-…)` alone) are left out.
 */
function fontStacks(string $stylesheet): array
{
    preg_match_all('/(--font-(?:sans|mono|serif|heading|display|logo)):\s*([^;]+);/', $stylesheet, $matches, PREG_SET_ORDER);

    $stacks = [];

    foreach ($matches as [, $property, $stack]) {
        $stack = preg_replace('/\s+/', ' ', trim($stack));

        if (Str::startsWith($stack, 'var(')) {
            continue;
        }

        $stacks[$property][] = $stack;
    }

    return $stacks;
}

/** A signed-in owner with a workspace, for the page that renders the app. */
function signedInOwner(): User
{
    $account = Account::factory()->create();
    $owner = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $owner->id]);
    $workspace = Workspace::factory()->create(['account_id' => $account->id, 'user_id' => $owner->id]);
    $owner->update(['current_workspace_id' => $workspace->id]);

    return $owner->refresh();
}

test('an app page asks no font host for its fonts', function () {
    $this->actingAs(signedInOwner())
        ->get(route('app.settings.preferences'))
        ->assertOk()
        ->assertDontSee('fonts.googleapis.com')
        ->assertDontSee('fonts.gstatic.com');
});

test('a guest page asks no font host for its fonts', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('fonts.googleapis.com')
        ->assertDontSee('fonts.gstatic.com');
});

test('the stylesheet imports every face from its self-hosted package', function (string $face) {
    expect(File::get(resource_path('css/app.css')))->toContain("@import '@fontsource-variable/{$face}");
})->with(array_keys(SELF_HOSTED_FACES));

test('every face the app names is served from the build, with a fallback stack behind it', function () {
    $stacks = fontStacks(File::get(resource_path('css/app.css')));
    $manifest = File::json(base_path('package.json'));

    foreach (SELF_HOSTED_FACES as $face => ['family' => $family, 'property' => $property, 'generic' => $generic]) {
        expect($stacks)->toHaveKey($property);
        expect(array_unique($stacks[$property]))->toHaveCount(1, "The light and dark blocks disagree on {$property}.");

        foreach ($stacks[$property] as $stack) {
            expect($stack)->toStartWith("'{$family}'", "'{$property}' must name the face the build serves.");
            expect(Str::contains($stack, $generic))->toBeTrue("'{$property}' must name a generic family behind it.");
            expect(Str::contains($stack, ','))->toBeTrue("'{$property}' must carry a fallback stack.");
        }

        expect($manifest['devDependencies'])->toHaveKey("@fontsource-variable/{$face}");
    }
});

test('every font-family the stylesheet declares names a fallback', function () {
    $generics = ['sans-serif', 'serif', 'monospace'];
    $declarations = [];

    foreach (File::glob(resource_path('css/*.css')) as $file) {
        preg_match_all('/font-family:\s*([^;}]+)/', File::get($file), $matches);
        array_push($declarations, ...$matches[1]);
    }

    expect($declarations)->not->toBeEmpty();

    foreach ($declarations as $stack) {
        $stack = preg_replace('/\s+/', ' ', trim($stack));

        expect(Str::endsWith($stack, $generics))->toBeTrue("'{$stack}' has no generic family behind it.");
    }
});

test('the app and the public pages point at no font host', function () {
    $hosts = ['fonts.googleapis.com', 'fonts.gstatic.com'];
    $binaries = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'woff', 'woff2'];

    $sources = collect(File::allFiles(resource_path('js')))
        ->merge(File::files(resource_path('css')))
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->push(resource_path('views/app.blade.php'))
        ->reject(fn (string $path): bool => in_array(pathinfo($path, PATHINFO_EXTENSION), $binaries, true));

    foreach ($sources as $path) {
        foreach ($hosts as $host) {
            expect(Str::contains(File::get($path), $host))->toBeFalse(
                "{$path} asks {$host}: the app serves its fonts itself (the emailed HTML is the one exception).",
            );
        }
    }
});

test('the cover image draws in the faces the stylesheet serves', function () {
    $theme = File::get(resource_path('js/lib/shareCard/theme.ts'));

    expect($theme)->toContain("'Inter Variable'")->toContain("'Outfit Variable'");
});
