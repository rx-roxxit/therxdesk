<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    $folder = Str::random(8);
    $this->base = storage_path("framework/testing/agent-rules-{$folder}");

    File::ensureDirectoryExists("{$this->base}/.ai/rules");
    File::ensureDirectoryExists("{$this->base}/.claude");

    File::put("{$this->base}/AGENTS.md", <<<'MD'
        <laravel-boost-guidelines>
        === php rules ===

        # PHP

        - Use curly braces.

        === herd rules ===

        # Herd

        - Use Herd.
        </laravel-boost-guidelines>

        # Project-Specific Rules

        ## Git

        - Never push. Before editing, open @.ai/rules/index.md, not `@.ai/rules/index.md` or @tabler/icons-vue.

        ## X polls

        ```md
        ## Not a heading inside a fence
        ```
        - A poll has 2 to 4 choices.
        MD);

    File::put("{$this->base}/.ai/rules/index.md", "# Project Rules Index\n");
    File::put("{$this->base}/.ai/rules/post.md", "---\npaths:\n  - 'app/Actions/Post/**'\n---\n\n# Post\n");

    File::put("{$this->base}/.claude/agent-rules.php", <<<'PHP'
        <?php

        return [
            'boost-php' => ['**/*.php'],
            'boost-herd' => false,
            'git' => true,
            'x-polls' => ['app/Support/Social/XPoll.php', 'resources/js/**/*Poll*'],
        ];
        PHP);
});

afterEach(function () {
    File::deleteDirectory($this->base);
});

test('the committed rules match AGENTS.md, .ai/rules and the map', function () {
    $this->artisan('rxdesk:agent-rules', ['--check' => true])->assertSuccessful();
});

test('each section becomes a rule that loads at launch, on its paths, or not at all', function () {
    $this->artisan('rxdesk:agent-rules', ['--base' => $this->base])->assertSuccessful();

    $output = "{$this->base}/.claude/rules/upstream";

    expect(File::get("{$output}/agents/x-polls.md"))
        ->toStartWith("---\npaths:\n  - \"app/Support/Social/XPoll.php\"\n  - \"resources/js/**/*Poll*\"\n---\n")
        ->toContain("## X polls\n\n```md\n## Not a heading inside a fence\n```\n- A poll has 2 to 4 choices.\n")
        ->and(File::get("{$output}/agents/git.md"))->not->toContain('paths:')
        ->and(File::get("{$output}/agents/boost-php.md"))->toContain("=== php rules ===\n\n# PHP\n\n- Use curly braces.\n")
        ->and(File::exists("{$output}/agents/boost-herd.md"))->toBeFalse()
        ->and(File::exists("{$output}/agents/not-a-heading-inside-a-fence.md"))->toBeFalse();
});

test('an @ that names a project file is turned into a code span so it never imports the file', function () {
    $this->artisan('rxdesk:agent-rules', ['--base' => $this->base])->assertSuccessful();

    expect(File::get("{$this->base}/.claude/rules/upstream/agents/git.md"))
        ->toContain('open `.ai/rules/index.md`, not `@.ai/rules/index.md` or @tabler/icons-vue.');
});

test('.ai/rules are copied without the index, and one without paths is refused', function () {
    $this->artisan('rxdesk:agent-rules', ['--base' => $this->base])->assertSuccessful();

    $output = "{$this->base}/.claude/rules/upstream/ai";

    expect(File::get("{$output}/post.md"))->toBe(File::get("{$this->base}/.ai/rules/post.md"))
        ->and(File::exists("{$output}/index.md"))->toBeFalse();

    File::put("{$this->base}/.ai/rules/jobs.md", "# Jobs\n");

    $this->artisan('rxdesk:agent-rules', ['--base' => $this->base])
        ->expectsOutputToContain('.ai/rules/jobs.md has no paths: frontmatter')
        ->assertFailed();
});

test('a section the map does not place fails without writing anything', function () {
    File::append("{$this->base}/AGENTS.md", "\n## Queue slots\n\n- A queued post keeps its slot.\n");

    $this->artisan('rxdesk:agent-rules', ['--base' => $this->base])
        ->expectsOutputToContain('AGENTS.md sections missing from .claude/agent-rules.php: queue-slots')
        ->assertFailed();

    expect(File::isDirectory("{$this->base}/.claude/rules/upstream"))->toBeFalse();
});

test('a map entry for a section AGENTS.md dropped fails', function () {
    File::put("{$this->base}/.claude/agent-rules.php", <<<'PHP'
        <?php

        return [
            'boost-php' => true,
            'boost-herd' => false,
            'git' => true,
            'x-polls' => true,
            'offers' => true,
        ];
        PHP);

    $this->artisan('rxdesk:agent-rules', ['--base' => $this->base])
        ->expectsOutputToContain('names sections AGENTS.md no longer has: offers')
        ->assertFailed();
});

test('the check fails on a stale or hand-edited rule and a rewrite removes the stale one', function () {
    $this->artisan('rxdesk:agent-rules', ['--base' => $this->base])->assertSuccessful();

    $output = "{$this->base}/.claude/rules/upstream";
    File::put("{$output}/agents/removed-section.md", "## Removed\n");
    File::append("{$output}/agents/git.md", "- Edited by hand.\n");

    $this->artisan('rxdesk:agent-rules', ['--base' => $this->base, '--check' => true])
        ->expectsOutputToContain('agents/git.md, agents/removed-section.md')
        ->assertFailed();

    $this->artisan('rxdesk:agent-rules', ['--base' => $this->base])->assertSuccessful();

    expect(File::exists("{$output}/agents/removed-section.md"))->toBeFalse()
        ->and(File::get("{$output}/agents/git.md"))->not->toContain('Edited by hand');

    $this->artisan('rxdesk:agent-rules', ['--base' => $this->base, '--check' => true])->assertSuccessful();
});
