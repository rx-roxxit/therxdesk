<?php

declare(strict_types=1);

use App\Enums\User\Locale;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

test('no lang value says TryPost', function (string $locale) {
    $offenders = collect(File::files(lang_path($locale)))
        ->flatMap(fn (SplFileInfo $file): array => collect(Arr::dot(require $file->getPathname()))
            ->filter(fn (mixed $value): bool => is_string($value) && Str::contains($value, 'TryPost'))
            ->keys()
            ->map(fn (string $key): string => "{$file->getFilenameWithoutExtension()}.{$key}")
            ->all())
        ->all();

    expect($offenders)->toBe([], 'Run `php artisan rxdesk:rebrand-lang`.');
})->with(Locale::values());
