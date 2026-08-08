<?php

declare(strict_types=1);

use App\Models\QuestionOption;
use App\Support\OptionTextAnalyser;

/**
 * Regression suite for docs/adr/003.
 *
 * Real wording from the client's question bank drove these cases: it numbers options
 * O1..O4, writes "All above" without "of the", and cross-references with digits
 * ("Both 1 and 2"). The original implementation caught none of those, which would
 * have shuffled "All above" into position 2 and rendered the question nonsense.
 */
it('pins summary options to the last position', function (string $text): void {
    expect(OptionTextAnalyser::shouldPinLast($text))->toBeTrue("'{$text}' should pin last");
})->with([
    'All above',                    // <- the client's actual wording
    'None above',
    'All the above',
    'All of the above',
    'None of the above',
    'All of these',
    'None of these',
    'Any of the above',
    'All the options',
    'All of the above are correct',
    'None of these is true',
    '  all   ABOVE  ',              // whitespace and casing
    'All above.',
]);

it('disables shuffling when an option references other options', function (string $text): void {
    expect(OptionTextAnalyser::referencesOtherOptions($text))->toBeTrue("'{$text}' references others");
})->with([
    'Both 1 and 2',                 // <- digits, because this bank numbers options
    '1 and 3',
    'Only 2 and 4',
    '1 & 3',
    '2, 3 and 4',
    'Both A and B',
    'A and C only',
    'Both (1) and (2)',
    'Either 1 or 2',
    '1 and 2 are correct',
]);

it('leaves genuine medical content alone', function (string $text): void {
    expect(OptionTextAnalyser::shouldPinLast($text))->toBeFalse("'{$text}' must not pin")
        ->and(OptionTextAnalyser::referencesOtherOptions($text))->toBeFalse("'{$text}' is not a reference");
})->with([
    'Calcium',
    'Radial nerve',
    '60-100 bpm',                   // numbers joined by a dash, not a reference
    'Vitamin B12',                  // letter+digits, not an option letter
    '12 pairs',
    '1,25-dihydroxyvitamin D',      // comma between numbers, still content
    'Type 1 and type 2 diabetes',   // "1 and 2" inside a real sentence
    'Left ventricle',
    'Grade 3 or 4 haemorrhage occurring after the first trimester of pregnancy',
    'Nothing by mouth',
    'A wave in the jugular venous pulse',
]);

it('reports a shuffle-safe question as shuffleable, pinning the summary option', function (): void {
    // The client's question 1, verbatim.
    $verdict = OptionTextAnalyser::analyseQuestion([
        'a' => 'Calcium',
        'b' => 'Copper',
        'c' => 'Selenium',
        'd' => 'All above',
    ]);

    expect($verdict['shuffle_options'])->toBeTrue()
        ->and($verdict['pin_last'])->toBe(['d'])
        ->and($verdict['reason'])->toBeNull();
});

it('disables shuffling for a question whose option points at other options', function (): void {
    $verdict = OptionTextAnalyser::analyseQuestion([
        'a' => 'Both 1 and 2',
        'b' => 'Cardiac tamponade is likely',
        'c' => 'Constrictive pericarditis is excluded',
        'd' => 'None above',
    ]);

    expect($verdict['shuffle_options'])->toBeFalse()
        ->and($verdict['pin_last'])->toBe([])
        ->and($verdict['reason'])->toBe('option_a_references_other_options');
});

it('refuses to shuffle when two options both claim the last position', function (): void {
    $verdict = OptionTextAnalyser::analyseQuestion([
        'a' => 'Calcium',
        'b' => 'Copper',
        'c' => 'All of the above',
        'd' => 'None of the above',
    ]);

    expect($verdict['shuffle_options'])->toBeFalse()
        ->and($verdict['reason'])->toBe('multiple_summary_options');
});

it('shuffles an ordinary question freely', function (): void {
    $verdict = OptionTextAnalyser::analyseQuestion([
        'a' => 'Propranolol', 'b' => 'Nebivolol', 'c' => 'Timolol', 'd' => 'Pindolol',
    ]);

    expect($verdict['shuffle_options'])->toBeTrue()
        ->and($verdict['pin_last'])->toBe([]);
});

it('keeps the model helpers in agreement with the analyser', function (): void {
    expect(QuestionOption::isAllOrNoneOfTheAbove('All above'))->toBeTrue()
        ->and(QuestionOption::textReferencesOtherOptions('Both 1 and 2'))->toBeTrue();
});
