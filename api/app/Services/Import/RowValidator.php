<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Question;

/**
 * Validates a single import row against the spec in docs/import-format.md.
 *
 * Returns ['errors' => [...], 'warnings' => [...], 'hash' => string|null].
 * A row with any error is rejected. A row with only warnings is importable.
 */
class RowValidator
{
    /** @var array<string, true> hashes seen so far in this file (in-file dedup) */
    private array $seenHashes = [];

    /** @var array<string, true> hashes already in the target level (DB dedup) */
    private array $existingHashes;

    public function __construct(int $levelId)
    {
        /** @var array<string, true> $hashes */
        $hashes = Question::forLevel($levelId)
            ->active()
            ->pluck('question_hash')
            ->mapWithKeys(fn (string $h) => [$h => true])
            ->all();

        $this->existingHashes = $hashes;
    }

    /**
     * @param  array<string, string|null>  $row  Normalised column names → values
     * @return array{errors: list<string>, warnings: list<string>, hash: string|null, correct_option: string|null}
     */
    public function validate(array $row): array
    {
        $errors = [];
        $warnings = [];

        // --- Required fields ---
        $question = $row['question'] ?? '';
        $optionA = $row['optiona'] ?? '';
        $optionB = $row['optionb'] ?? '';
        $optionC = $row['optionc'] ?? '';
        $optionD = $row['optiond'] ?? '';
        $correctOption = $row['correctoption'] ?? '';
        $explanation = $row['explanation'] ?? '';
        $topic = $row['topic'] ?? null;
        $status = $row['status'] ?? 'active';

        // question
        if (strlen($question) < 10) {
            $errors[] = 'missing_question';
        } elseif (strlen($question) > 2000) {
            $errors[] = 'text_too_long';
        } elseif (strlen($question) > 600) {
            $warnings[] = 'long_question';
        }

        // options
        foreach (['optiona' => $optionA, 'optionb' => $optionB, 'optionc' => $optionC, 'optiond' => $optionD] as $key => $val) {
            if (strlen($val) < 1) {
                $errors[] = 'missing_option';
                break; // one error is enough
            }
            if (strlen($val) > 500) {
                $errors[] = 'text_too_long';
                break;
            }
        }

        // identical options
        $optionTexts = array_filter([$optionA, $optionB, $optionC, $optionD], fn ($v) => $v !== '');
        if (count($optionTexts) !== count(array_unique($optionTexts))) {
            $errors[] = 'identical_options';
        }

        // correct_option
        $resolved = self::resolveCorrectOption($correctOption);
        if ($correctOption === '') {
            $errors[] = 'missing_correct_option';
        } elseif ($resolved === null) {
            $errors[] = 'invalid_correct_option';
        }

        // explanation
        if (strlen($explanation) < 5) {
            $errors[] = 'missing_explanation';
        } elseif (strlen($explanation) > 2000) {
            $errors[] = 'text_too_long';
        } elseif (strlen($explanation) < 25) {
            $warnings[] = 'short_explanation';
        }

        // topic (warning only)
        if (! $topic || trim($topic) === '') {
            $warnings[] = 'no_topic';
        }

        // HTML safety check (simplified)
        foreach ([$question, $optionA, $optionB, $optionC, $optionD, $explanation] as $text) {
            if (preg_match('/<(script|iframe|style|link|object|embed)/i', $text)) {
                $errors[] = 'unsafe_html';
                break;
            }
        }

        // --- Deduplication ---
        $hash = null;
        if (empty($errors)) {
            $hash = Question::makeHash($question, [$optionA, $optionB, $optionC, $optionD]);

            if (isset($this->seenHashes[$hash])) {
                $errors[] = 'duplicate_in_file';
            } elseif (isset($this->existingHashes[$hash])) {
                $errors[] = 'duplicate_in_level';
            } else {
                $this->seenHashes[$hash] = true;
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'hash' => $hash,
            'correct_option' => $resolved,
        ];
    }

    /**
     * Resolve flexible correct_option input to a canonical lowercase letter.
     * Accepts: A, a, 1, (A), A., option A, etc.
     */
    public static function resolveCorrectOption(string $input): ?string
    {
        $input = strtolower(trim($input));

        // Direct letter
        if (in_array($input, ['a', 'b', 'c', 'd'], true)) {
            return $input;
        }

        // Numeric 1–4
        $numMap = ['1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd'];
        if (isset($numMap[$input])) {
            return $numMap[$input];
        }

        // Patterns like (A), A., option A
        if (preg_match('/[(\s]?([abcd])[).\s]?/i', $input, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }
}
