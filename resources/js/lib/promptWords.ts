export const PROMPT_MIN_WORDS = 4;
export const PROMPT_MAX_LENGTH = 10000;

const CJK_PATTERN = /[\p{Script=Han}\p{Script=Hiragana}\p{Script=Katakana}]/gu;
const UNSPACED_SCRIPT_PATTERN =
    /[\p{Script=Thai}\p{Script=Lao}\p{Script=Khmer}\p{Script=Myanmar}]+/gu;

const countUnspacedRuns = (runs: string[]): number => {
    if (typeof Intl === 'undefined') {
        return runs.length;
    }

    if (typeof Intl.Segmenter !== 'function') {
        return runs.length;
    }

    const segmenter = new Intl.Segmenter('und', { granularity: 'word' });
    let words = 0;

    for (const run of runs) {
        for (const segment of segmenter.segment(run)) {
            if (segment.isWordLike) {
                words += 1;
            }
        }
    }

    return words;
};

export const countPromptWords = (text: string): number => {
    const unspacedRuns = text.match(UNSPACED_SCRIPT_PATTERN);
    let segmentedWords = 0;
    let remaining = text;

    if (unspacedRuns !== null) {
        segmentedWords = countUnspacedRuns(unspacedRuns);
        remaining = text.replace(UNSPACED_SCRIPT_PATTERN, ' ');
    }

    const ideographMatches = remaining.match(CJK_PATTERN);
    let ideographs = 0;

    if (ideographMatches !== null) {
        ideographs = ideographMatches.length;
    }

    const rest = remaining
        .replace(CJK_PATTERN, ' ')
        .trim()
        .split(/\s+/u)
        .filter(Boolean).length;

    return segmentedWords + ideographs + rest;
};
