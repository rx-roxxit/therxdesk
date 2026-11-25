<?php

declare(strict_types=1);

use App\Enums\User\Locale;

test('the help menu links to the fork source code, not to TryPost services', function () {
    expect(file_get_contents(resource_path('js/components/WorkspaceMenuContent.vue')))
        ->toContain("'https://github.com/rx-roxxit/therxdesk'")
        ->toContain("\$t('rxdesk.help.source_code')")
        ->not->toContain('trypost.it')
        ->not->toContain('github.com/trypostit');
});

test('the source code label is written in every locale', function (string $locale) {
    $label = __('rxdesk.help.source_code', [], $locale);

    expect($label)->not->toBeEmpty();

    if ($locale === Locale::English->value) {
        expect($label)->toBe('Source code');

        return;
    }

    expect($label)->not->toBe('Source code');
})->with(Locale::values());
