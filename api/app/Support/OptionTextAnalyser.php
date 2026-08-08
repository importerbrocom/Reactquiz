<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decides whether an answer option is safe to reposition.
 *
 * Per-exposure option shuffling (docs/adr/003) is what stops students memorising
 * "the answer is the second one". But two families of option text break when moved:
 *
 *   1. "All of the above" / "None above"  — meaningful only in the last position,
 *      so it is PINNED there while the other choices shuffle around it.
 *   2. "Both 1 and 2" / "A and C only"    — the reference IS the meaning, so
 *      shuffling is DISABLED for the whole question.
 *
 * Real banks write these many ways. This source, for instance, numbers its options
 * O1..O4 and writes "All above" (no "of the") and "Both 1 and 2" (digits, not
 * letters) — none of which an "all of the above" check would catch.
 */
final class OptionTextAnalyser
{
    /**
     * Summary phrases that must stay last.
     *
     * Covers: "All above", "All the above", "All of the above", "All of these",
     * "None above", "None of these", "Any of the above", "All the options",
     * and the same with a trailing "are correct" / "is true".
     */
    private const PIN_LAST = '/^\s*(?:all|none|any)\s+(?:of\s+)?(?:the\s+)?'
        .'(?:above|below|these|those|option|options|answers?|statements?)'
        .'(?:\s+(?:are|is))?(?:\s+(?:correct|true|right))?\s*[.!]?\s*$/i';

    /**
     * Options whose text points at other options by number or letter.
     *
     * Anchored at both ends and limited to bare references joined by and/&/comma,
     * so genuine content is not caught: "60-100 bpm", "Vitamin B12", "12 pairs"
     * and "1,25-dihydroxyvitamin D" all pass through untouched.
     */
    private const CROSS_REFERENCE = '/^\s*(?:both|only|either|neither)?\s*'
        .'\(?[1-4a-dA-D]\)?\s*'
        .'(?:(?:,|&|\+|and|or)\s*\(?[1-4a-dA-D]\)?\s*)+'
        .'(?:only|alone)?(?:\s+(?:are|is))?(?:\s+(?:correct|true|right))?\s*[.!]?\s*$/i';

    public static function shouldPinLast(string $text): bool
    {
        return (bool) preg_match(self::PIN_LAST, self::normalise($text));
    }

    public static function referencesOtherOptions(string $text): bool
    {
        $normalised = self::normalise($text);

        // Bare references are short by nature; the length bound is a cheap guard
        // against a long sentence that happens to end in a matching fragment.
        if (mb_strlen($normalised) > 48) {
            return false;
        }

        return (bool) preg_match(self::CROSS_REFERENCE, $normalised);
    }

    /**
     * Verdict for a whole question.
     *
     * @param  array<string, string>  $options  option_key => text
     * @return array{shuffle_options: bool, pin_last: array<int, string>, reason: string|null}
     */
    public static function analyseQuestion(array $options): array
    {
        $pinLast = [];
        $blocking = null;

        foreach ($options as $key => $text) {
            if (self::referencesOtherOptions($text)) {
                $blocking ??= $key;
            } elseif (self::shouldPinLast($text)) {
                $pinLast[] = (string) $key;
            }
        }

        // More than one summary option ("All above" AND "None above") cannot both be
        // last, so the safest answer is to leave that question's order alone.
        if ($blocking === null && count($pinLast) > 1) {
            return [
                'shuffle_options' => false,
                'pin_last' => [],
                'reason' => 'multiple_summary_options',
            ];
        }

        if ($blocking !== null) {
            return [
                'shuffle_options' => false,
                'pin_last' => [],
                'reason' => "option_{$blocking}_references_other_options",
            ];
        }

        return [
            'shuffle_options' => true,
            'pin_last' => $pinLast,
            'reason' => null,
        ];
    }

    private static function normalise(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    }
}
