<?php

declare(strict_types=1);

use App\Enums\User\Locale;
use Illuminate\Support\Arr;

test('the availability copy exists in every locale, keeps the network placeholder and is not English', function (string $locale) {
    $english = require lang_path('en/rxdesk.php');
    $copy = require lang_path("{$locale}/rxdesk.php");

    foreach (['availability.schedule_refused', 'availability.banner', 'publishing_block.unavailable'] as $key) {
        $value = Arr::get($copy, $key);

        expect($value)->toBeString("{$locale} is missing {$key}")
            ->not->toBeEmpty("{$locale} has an empty {$key}")
            ->and(str_contains((string) $value, ':network'))->toBeTrue("{$locale} lost the :network placeholder in {$key}");

        if ($locale !== Locale::English->value) {
            expect($value)->not->toBe(Arr::get($english, $key), "{$locale} still uses English for {$key}");
        }
    }
})->with(Locale::values());
