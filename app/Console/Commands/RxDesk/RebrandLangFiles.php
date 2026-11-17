<?php

declare(strict_types=1);

namespace App\Console\Commands\RxDesk;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Rewrites "TryPost" to "The RX Desk" in the values of every locale's upstream
 * lang files, never in a key. It is idempotent: run it again after each upstream
 * merge so new upstream strings never show TryPost. The RX Desk's own lang file
 * (`rxdesk.php`) is never touched.
 */
class RebrandLangFiles extends Command
{
    protected $signature = 'rxdesk:rebrand-lang {--path= : The lang folder to rewrite (defaults to lang/)}';

    protected $description = 'Rewrite TryPost to The RX Desk in the values of every locale\'s lang files';

    /**
     * English articles that would read "a The RX Desk account", applied to
     * `en` only: in other languages the same letters are other words
     * (Italian "Connettiti a TryPost").
     *
     * @var array<string, string>
     */
    private const array ENGLISH_PHRASES = [
        '/\bThe TryPost\b/' => 'The RX Desk',
        '/\bthe TryPost\b/' => 'the RX Desk',
        '/\bA TryPost\b/' => 'An RX Desk',
        '/\ba TryPost\b/' => 'an RX Desk',
        '/\banother TryPost\b/' => 'another RX Desk',
    ];

    /**
     * Every locale. `strtr` tries the longest match first, so "TryPost.it"
     * never becomes "The RX Desk.it".
     *
     * @var array<string, string>
     */
    private const array NAMES = [
        'TryPost.it' => 'The RX Desk',
        'TryPost' => 'The RX Desk',
    ];

    public function handle(): int
    {
        $path = $this->option('path') ?: lang_path();
        $changed = 0;

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'rxdesk.php') {
                continue;
            }

            $isEnglish = Str::before($file->getRelativePath(), DIRECTORY_SEPARATOR) === 'en';

            if ($this->rebrand($file->getPathname(), $isEnglish)) {
                $changed++;
            }
        }

        $this->info("Rebranded {$changed} lang file(s).");

        return self::SUCCESS;
    }

    /**
     * Rewrites one file, returning whether anything changed.
     */
    private function rebrand(string $file, bool $isEnglish): bool
    {
        $source = File::get($file);
        $tokens = token_get_all($source);
        $rewritten = '';

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                $rewritten .= $token;

                continue;
            }

            if ($token[0] === T_CONSTANT_ENCAPSED_STRING && ! $this->isKey($tokens, $index)) {
                $rewritten .= $this->rebrandValue($token[1], $isEnglish);

                continue;
            }

            $rewritten .= $token[1];
        }

        if ($rewritten === $source) {
            return false;
        }

        File::put($file, $rewritten);

        return true;
    }

    private function rebrandValue(string $value, bool $isEnglish): string
    {
        if ($isEnglish) {
            $value = preg_replace(array_keys(self::ENGLISH_PHRASES), array_values(self::ENGLISH_PHRASES), $value);
        }

        return strtr($value, self::NAMES);
    }

    /**
     * A string is a key when the next meaningful token is `=>`.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function isKey(array $tokens, int $index): bool
    {
        $total = count($tokens);

        for ($cursor = $index + 1; $cursor < $total; $cursor++) {
            $next = $tokens[$cursor];

            if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($next) && $next[0] === T_DOUBLE_ARROW;
        }

        return false;
    }
}
