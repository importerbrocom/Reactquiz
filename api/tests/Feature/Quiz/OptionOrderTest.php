<?php

declare(strict_types=1);

use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\Quiz\OptionOrderService;

/**
 * docs/adr/003 — per-EXPOSURE option ordering.
 *
 * This is the answer to the problem that started the design: a student who has seen a
 * question before may remember "the answer was the second one" without remembering the
 * medicine. Two properties have to hold at the same time, and they pull in opposite
 * directions:
 *
 *   STABLE   while the student is looking at the question, so a refresh, an offline
 *            reload or a resume never reshuffles the options under their cursor.
 *   MOVING   the next time they are asked, so position carries no information.
 *
 * The seed is (scope, question, submission_count). submission_count only advances when
 * they answer — which is exactly the boundary between those two properties.
 */
beforeEach(function (): void {
    $this->orders = app(OptionOrderService::class);
    $this->level = Level::factory()->create();
});

function makeQuestion(array $attributes = []): Question
{
    return Question::factory()->forLevel(test()->level)->create($attributes);
}

// ------------------------------------------------------------- it is a permutation ----

it('returns every authored option exactly once', function (): void {
    $question = makeQuestion();

    $order = $this->orders->for($question, scope: 'd1:1:1', submissionCount: 0);

    expect($order)->toHaveCount(4)
        ->and(array_unique($order))->toHaveCount(4)
        ->and(collect($order)->sort()->values()->all())->toBe(['a', 'b', 'c', 'd']);
});

it('never loses or duplicates an option across many exposures', function (): void {
    $question = makeQuestion();

    foreach (range(0, 50) as $exposure) {
        $order = $this->orders->for($question, scope: 'd1:1:1', submissionCount: $exposure);

        expect(collect($order)->sort()->values()->all())->toBe(['a', 'b', 'c', 'd']);
    }
});

// ------------------------------------------------------------------------ stability ----

it('holds the order steady while the student has not answered', function (): void {
    // A refresh, a reconnect, a resume from the service worker: all the same exposure.
    $question = makeQuestion();

    $first = $this->orders->for($question, scope: 'd7:7:1', submissionCount: 3);

    foreach (range(1, 10) as $ignored) {
        expect($this->orders->for($question, scope: 'd7:7:1', submissionCount: 3))->toBe($first);
    }
});

it('holds steady across separate service instances', function (): void {
    // Nothing is memoised in the instance, so two web workers agree.
    $question = makeQuestion();

    expect((new OptionOrderService)->for($question, 'd1:1:1', 2))
        ->toBe((new OptionOrderService)->for($question, 'd1:1:1', 2));
});

// -------------------------------------------------------------------------- movement ----

it('moves the options once the student has answered', function (): void {
    // Across a realistic set of questions, answering must reshuffle most of them.
    $questions = Question::factory()->count(40)->forLevel($this->level)->create();

    $changed = $questions->filter(fn (Question $q): bool => $this->orders->for($q, 'd1:1:1', 0)
        !== $this->orders->for($q, 'd1:1:1', 1));

    // 1 in 24 permutations is the identity, so a handful may legitimately repeat.
    expect($changed->count())->toBeGreaterThan(30);
});

it('does not park the correct answer in one position', function (): void {
    // The property that actually defeats position memorisation: asked repeatedly, the
    // correct option must travel around the four slots.
    $question = makeQuestion();
    $correct = $question->correct_option->value;

    $positions = collect(range(0, 39))
        ->map(fn (int $exposure): int => array_search(
            $correct,
            $this->orders->for($question, 'd1:1:1', $exposure),
            true,
        ))
        ->countBy();

    expect($positions->keys())->toHaveCount(4)
        // Roughly uniform: 40 exposures over 4 slots, no slot starved or hogged.
        ->and($positions->min())->toBeGreaterThanOrEqual(3)
        ->and($positions->max())->toBeLessThanOrEqual(20);
});

it('shows different students different orders on the same day', function (): void {
    // Two students on day 1, first attempt, neither has answered yet.
    $questions = Question::factory()->count(40)->forLevel($this->level)->create();

    $differing = $questions->filter(fn (Question $q): bool => $this->orders->for($q, 'd101:1:1', 0)
        !== $this->orders->for($q, 'd202:1:1', 0));

    expect($differing->count())->toBeGreaterThan(30);
});

it('shows a different order on a fresh attempt at the same day', function (): void {
    // Unlimited retries are allowed, so re-opening day 1 must not replay the layout.
    $questions = Question::factory()->count(40)->forLevel($this->level)->create();

    $differing = $questions->filter(fn (Question $q): bool => $this->orders->for($q, 'd1:1:1', 0)
        !== $this->orders->for($q, 'd1:1:2', 0));

    expect($differing->count())->toBeGreaterThan(30);
});

it('shows a different order in the month-end test than on the day', function (): void {
    // Business rule: the same questions come back at month end, shuffled.
    $questions = Question::factory()->count(40)->forLevel($this->level)->create();

    $differing = $questions->filter(fn (Question $q): bool => $this->orders->for($q, 'd1:1:1', 0)
        !== $this->orders->for($q, 't1', 0));

    expect($differing->count())->toBeGreaterThan(30);
});

// ------------------------------------------------------------------ opting out ----

it('leaves an unshufflable question in its authored order', function (): void {
    // "Both 1 and 2 are correct" only makes sense next to 1 and 2.
    $question = makeQuestion(['shuffle_options' => false]);

    foreach (range(0, 10) as $exposure) {
        expect($this->orders->for($question, 'd1:1:1', $exposure))->toBe(['a', 'b', 'c', 'd']);
    }
});

it('keeps a pinned option last however often it is asked', function (): void {
    // "All of the above" in slot two is a giveaway and reads as a typo.
    $question = makeQuestion();
    QuestionOption::query()
        ->where('question_id', $question->getKey())
        ->where('option_key', 'd')
        ->update(['pin_last' => true, 'option_text' => 'All of the above']);

    foreach (range(0, 20) as $exposure) {
        $order = $this->orders->for($question->refresh(), 'd1:1:1', $exposure);

        expect($order)->toHaveCount(4)
            ->and($order[3])->toBe('d');
    }
});

it('still moves the unpinned options around a pinned one', function (): void {
    $questions = Question::factory()->count(40)->forLevel($this->level)->create();
    QuestionOption::query()
        ->whereIn('question_id', $questions->modelKeys())
        ->where('option_key', 'd')
        ->update(['pin_last' => true]);

    $changed = $questions->filter(fn (Question $q): bool => $this->orders->for($q->refresh(), 'd1:1:1', 0)
        !== $this->orders->for($q->refresh(), 'd1:1:1', 1));

    // Only 3 options move now, so 1 in 6 exposures repeats by chance.
    expect($changed->count())->toBeGreaterThan(25);
});

it('respects the authored display order rather than the option key', function (): void {
    // An importer may write options out of alphabetical order; display_order wins.
    $question = makeQuestion(['shuffle_options' => false]);
    $keys = ['a' => 3, 'b' => 1, 'c' => 0, 'd' => 2];

    foreach ($keys as $key => $position) {
        QuestionOption::query()
            ->where('question_id', $question->getKey())
            ->where('option_key', $key)
            ->update(['display_order' => $position]);
    }

    expect($this->orders->for($question->refresh(), 'd1:1:1', 0))->toBe(['c', 'b', 'd', 'a']);
});

// ----------------------------------------------------------------------- scopes ----

it('builds a daily scope that separates enrolment, day and attempt', function (): void {
    expect($this->orders->dailyScope(11, 2, 3))->toBe('d11:2:3')
        ->and($this->orders->dailyScope(11, 2, 3))->not->toBe($this->orders->dailyScope(11, 3, 2))
        ->and($this->orders->dailyScope(1, 12, 3))->not->toBe($this->orders->dailyScope(11, 2, 3));
});

it('builds a test scope that cannot collide with a daily one', function (): void {
    expect($this->orders->testScope(1))->toBe('t1')
        ->and($this->orders->testScope(1))->not->toBe($this->orders->dailyScope(1, 1, 1));
});

// ------------------------------------------------------------------ efficiency ----

it('uses the already-loaded options instead of querying again', function (): void {
    // Called once per question on the day payload; an extra query here is 10 per day.
    $question = makeQuestion();
    $question->load('options');

    DB::enableQueryLog();
    $this->orders->for($question, 'd1:1:1', 0);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBe(0);
});
