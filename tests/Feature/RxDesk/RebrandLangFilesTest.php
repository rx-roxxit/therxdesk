<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $folder = Str::random(8);
    $this->langPath = storage_path("framework/testing/rebrand-lang-{$folder}");

    foreach (['en', 'ko', 'it'] as $locale) {
        File::ensureDirectoryExists("{$this->langPath}/{$locale}");
    }
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

test('it rewrites TryPost to The RX Desk in values and leaves keys alone', function () {
    File::put("{$this->langPath}/en/create.php", <<<'PHP'
        <?php

        return [
            'by_trypost' => 'By TryPost',
            'TryPost' => [
                'welcome' => 'Welcome to TryPost!',
                'signature' => "Paulo from TryPost.it",
                'possessive' => 'TryPost\'s data',
            ],
        ];
        PHP);
    File::put("{$this->langPath}/ko/create.php", <<<'PHP'
        <?php

        return [
            'by_trypost' => 'TryPost가 게시함',
        ];
        PHP);

    $this->artisan('rxdesk:rebrand-lang', ['--path' => $this->langPath])->assertSuccessful();

    expect(require "{$this->langPath}/en/create.php")->toBe([
        'by_trypost' => 'By The RX Desk',
        'TryPost' => [
            'welcome' => 'Welcome to The RX Desk!',
            'signature' => 'Paulo from The RX Desk',
            'possessive' => "The RX Desk's data",
        ],
    ])
        ->and(require "{$this->langPath}/ko/create.php")->toBe([
            'by_trypost' => 'The RX Desk가 게시함',
        ]);
});

test('english keeps its articles readable, other locales only swap the name', function () {
    File::put("{$this->langPath}/en/auth.php", <<<'PHP'
        <?php

        return [
            'team' => 'The TryPost Team',
            'app' => 'Open the TryPost app',
            'create' => 'Create a TryPost account',
            'sentence' => 'A TryPost workspace',
            'other' => 'Members without another TryPost workspace',
            'via' => 'Published via TryPost',
        ];
        PHP);
    File::put("{$this->langPath}/it/auth.php", <<<'PHP'
        <?php

        return [
            'connect' => 'Connettiti a TryPost',
        ];
        PHP);

    $this->artisan('rxdesk:rebrand-lang', ['--path' => $this->langPath])->assertSuccessful();

    expect(require "{$this->langPath}/en/auth.php")->toBe([
        'team' => 'The RX Desk Team',
        'app' => 'Open the RX Desk app',
        'create' => 'Create an RX Desk account',
        'sentence' => 'An RX Desk workspace',
        'other' => 'Members without another RX Desk workspace',
        'via' => 'Published via The RX Desk',
    ])
        ->and(require "{$this->langPath}/it/auth.php")->toBe([
            'connect' => 'Connettiti a The RX Desk',
        ]);
});

test('a second run changes nothing and rxdesk.php is never touched', function () {
    File::put("{$this->langPath}/en/auth.php", "<?php\n\nreturn [\n    'title' => 'Log in to TryPost', // TryPost login\n];\n");
    File::put("{$this->langPath}/en/rxdesk.php", "<?php\n\nreturn [\n    'credit' => 'Forked from TryPost',\n];\n");

    $this->artisan('rxdesk:rebrand-lang', ['--path' => $this->langPath])
        ->expectsOutput('Rebranded 1 lang file(s).')
        ->assertSuccessful();

    $firstRun = File::get("{$this->langPath}/en/auth.php");

    $this->artisan('rxdesk:rebrand-lang', ['--path' => $this->langPath])
        ->expectsOutput('Rebranded 0 lang file(s).')
        ->assertSuccessful();

    expect(File::get("{$this->langPath}/en/auth.php"))
        ->toBe($firstRun)
        ->toBe("<?php\n\nreturn [\n    'title' => 'Log in to The RX Desk', // TryPost login\n];\n")
        ->and(File::get("{$this->langPath}/en/rxdesk.php"))->toContain('Forked from TryPost');
});
