<?php

declare(strict_types=1);

namespace App\Support;

use IntlBreakIterator;

/**
 * Shared limits for prompts sent to the post assistant.
 */
final class AiPromptRules
{
    /**
     * Maximum prompt length (characters); mirrored by the frontend counter.
     */
    public const int PROMPT_MAX_LENGTH = 10000;

    public const int PROMPT_MIN_WORDS = 4;

    private const string CJK_PATTERN = '/[\p{Han}\p{Hiragana}\p{Katakana}]/u';

    private const string UNSPACED_SCRIPT_PATTERN = '/[\p{Thai}\p{Lao}\p{Khmer}\p{Myanmar}]+/u';

    /**
     * Counts whitespace-separated words, with each Han, Hiragana or Katakana
     * character counted as one word and unspaced Southeast Asian scripts
     * segmented with ICU word boundaries.
     */
    public static function wordCount(string $text): int
    {
        $segmentedWords = 0;

        if (preg_match_all(self::UNSPACED_SCRIPT_PATTERN, $text, $matches) > 0) {
            $segmentedWords = self::countUnspacedRuns($matches[0]);
            $text = (string) preg_replace(self::UNSPACED_SCRIPT_PATTERN, ' ', $text);
        }

        $ideographs = preg_match_all(self::CJK_PATTERN, $text);
        $rest = preg_split('/\s+/u', trim((string) preg_replace(self::CJK_PATTERN, ' ', $text)), -1, PREG_SPLIT_NO_EMPTY);

        return $segmentedWords + $ideographs + count($rest);
    }

    private static function countUnspacedRuns(array $runs): int
    {
        if (! class_exists(IntlBreakIterator::class)) {
            return count($runs);
        }

        $iterator = IntlBreakIterator::createWordInstance('und');

        if ($iterator === null) {
            return count($runs);
        }

        $words = 0;

        foreach ($runs as $run) {
            $iterator->setText($run);

            while ($iterator->next() !== IntlBreakIterator::DONE) {
                if ($iterator->getRuleStatus() !== IntlBreakIterator::WORD_NONE) {
                    $words++;
                }
            }
        }

        return $words;
    }
}
