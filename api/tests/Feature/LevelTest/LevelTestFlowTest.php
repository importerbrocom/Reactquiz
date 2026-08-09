<?php

declare(strict_types=1);

use App\Actions\LevelTest\StartLevelTestAction;
use App\Actions\LevelTest\SubmitLevelTestAction;
use App\Actions\LevelTest\SyncLevelTestAnswersAction;
use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\DTOs\Quiz\TestAnswerData;
use App\Enums\OptionKey;
use App\Enums\TestAttemptStatus;
use App\Events\LevelTestGraded;
use App\Exceptions\LevelTestNotEligibleException;
use App\Exceptions\TestAttemptClosedException;
use App\Exceptions\TestAttemptExpiredException;
use App\Models\LevelTestAnswer;
use App\Models\LevelTestAttempt;
use App\Models\Question;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\QuizScenario;

/**
 * The month-end test: the same 300 questions the student practised, in a different
 * order, graded server-side with no negative marking and unlimited retakes.
 */
beforeEach(function (): void {
    $this->start = app(StartLevelTestAction::class);
    $this->sync = app(SyncLevelTestAnswersAction::class);
    $this->submitTest = app(SubmitLevelTestAction::class);

    $this->scenario = QuizScenario::make(days: 4, perDay: 3);
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
});

/** @param array<int, array{0: int, 1: ?OptionKey}> $pairs */
function batch(array $pairs, bool $flagged = false): array
{
    return array_map(
        fn (array $pair): TestAnswerData => new TestAnswerData(
            questionId: $pair[0],
            selectedOption: $pair[1],
            isFlagged: $flagged,
            timeSpentMs: 3_000,
        ),
        $pairs,
    );
}

/** Answer the whole paper; $correctFor decides which questions are answered correctly. */
function answerPaper(LevelTestAttempt $attempt, callable $correctFor): void
{
    $questions = Question::query()
        ->whereIn('id', $attempt->question_order)
        ->get(['id', 'correct_option'])
        ->keyBy('id');

    $pairs = [];

    foreach ($attempt->question_order as $index => $questionId) {
        /** @var Question $question */
        $question = $questions->get($questionId);
        $correct = $correctFor($index);

        $pairs[] = [$questionId, $correct === null
            ? null
            : ($correct
                ? $question->correct_option
                : collect(OptionKey::cases())
                    ->first(fn (OptionKey $k): bool => $k !== $question->correct_option
                        && in_array($k->value, ['a', 'b', 'c', 'd'], true))),
        ];
    }

    foreach (array_chunk($pairs, 20) as $chunk) {
        app(SyncLevelTestAnswersAction::class)($attempt->refresh(), batch($chunk), (string) Str::uuid7());
    }
}

// ---------------------------------------------------------------- eligibility ----

it('refuses to open the test while any day is outstanding', function (): void {
    $this->scenario->completeDaysUpTo(3);   // one day short of the four

    expect(fn () => ($this->start)($this->scenario->enrolment->refresh()))
        ->toThrow(LevelTestNotEligibleException::class);

    expect(LevelTestAttempt::query()->count())->toBe(0);
});

it('names the missing days when it refuses', function (): void {
    $this->scenario->completeDay(1);
    $this->scenario->completeDay(3);

    try {
        ($this->start)($this->scenario->enrolment->refresh());
        $this->fail('the test should not have opened');
    } catch (LevelTestNotEligibleException $e) {
        // 423 Locked, not 403: the student is not forbidden, the content is not yet open.
        expect($e->status())->toBe(423)
            ->and($e->meta()['completed_days'])->toBe(2)
            ->and($e->meta()['required_days'])->toBe(4)
            ->and($e->meta()['missing_days'])->toBe([2, 4]);
    }
});

it('opens the test once every day is complete', function (): void {
    $this->scenario->completeAllDays();

    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    expect($attempt->status)->toBe(TestAttemptStatus::InProgress)
        ->and($attempt->attempt_number)->toBe(1)
        ->and($attempt->total_questions)->toBe(12)
        ->and($attempt->question_order)->toHaveCount(12);
});

// --------------------------------------------------------------- the paper ----

it('builds the paper from the questions the student actually practised', function (): void {
    // Not a fresh sample from the pool: retention of THEIR 12 questions is the point.
    $this->scenario->completeAllDays();
    $assigned = $this->scenario->enrolment->dayQuestions()->pluck('question_id')->sort()->values()->all();

    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    expect(collect($attempt->question_order)->sort()->values()->all())->toBe($assigned);
});

it('shuffles the paper out of day order', function (): void {
    // Otherwise question 1 is day 1 question 1, and the paper is a rerun of the month.
    $this->scenario = QuizScenario::realistic();
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
    $this->scenario->completeAllDays();

    $dayOrder = $this->scenario->enrolment->dayQuestions()
        ->orderBy('day_number')->orderBy('position')->pluck('question_id')->all();

    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    expect($attempt->question_order)->not->toBe($dayOrder)
        ->and($attempt->question_order)->toHaveCount(300);
});

it('gives two students different papers', function (): void {
    $this->scenario->completeAllDays();
    $mine = ($this->start)($this->scenario->enrolment->refresh())->question_order;

    $other = QuizScenario::make(days: 4, perDay: 3);
    app(AssignLevelQuestionsAction::class)($other->enrolment);
    $other->completeAllDays();
    $theirs = ($this->start)($other->enrolment->refresh())->question_order;

    // Different questions entirely (own level) — and, more to the point, a different
    // sequence, so screenshots of "question 5" are worthless between students.
    expect($mine)->not->toBe($theirs);
});

it('keeps the paper fixed for the life of the attempt', function (): void {
    // Pagination, resume and the review screen all index into question_order.
    $this->scenario->completeAllDays();

    $first = ($this->start)($this->scenario->enrolment->refresh());
    $resumed = ($this->start)($this->scenario->enrolment->refresh());

    expect($resumed->getKey())->toBe($first->getKey())
        ->and($resumed->question_order)->toBe($first->question_order)
        ->and(LevelTestAttempt::query()->count())->toBe(1);
});

it('reshuffles the paper on a retake', function (): void {
    $this->scenario = QuizScenario::realistic();
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
    $this->scenario->completeAllDays();

    $first = ($this->start)($this->scenario->enrolment->refresh());
    ($this->submitTest)($first);
    $second = ($this->start)($this->scenario->enrolment->refresh());

    expect($second->attempt_number)->toBe(2)
        ->and($second->question_order)->not->toBe($first->question_order)
        ->and($second->shuffle_seed)->not->toBe($first->shuffle_seed);
});

// ------------------------------------------------------------ answer syncing ----

it('saves a batch of answers without grading them', function (): void {
    // The sync path must be incapable of telling the student how they are doing.
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    $ids = array_slice($attempt->question_order, 0, 3);

    $result = ($this->sync)($attempt, batch([
        [$ids[0], OptionKey::A],
        [$ids[1], OptionKey::B],
        [$ids[2], null],
    ]), (string) Str::uuid7());

    expect($result->accepted)->toBe(3)
        ->and($result->answeredCount)->toBe(2)   // the null is "seen, not answered"
        ->and(LevelTestAnswer::query()->whereNotNull('is_correct')->count())->toBe(0);
});

it('lets a student change their mind', function (): void {
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    $questionId = $attempt->question_order[0];

    ($this->sync)($attempt, batch([[$questionId, OptionKey::A]]), (string) Str::uuid7());
    ($this->sync)($attempt->refresh(), batch([[$questionId, OptionKey::C]]), (string) Str::uuid7());

    $saved = LevelTestAnswer::query()->where('question_id', $questionId)->firstOrFail();

    expect($saved->selected_option)->toBe(OptionKey::C)
        ->and(LevelTestAnswer::query()->where('question_id', $questionId)->count())->toBe(1)
        ->and($attempt->refresh()->answered_count)->toBe(1);
});

it('lets a student clear an answer', function (): void {
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    $questionId = $attempt->question_order[0];

    ($this->sync)($attempt, batch([[$questionId, OptionKey::A]]), (string) Str::uuid7());
    ($this->sync)($attempt->refresh(), batch([[$questionId, null]]), (string) Str::uuid7());

    expect($attempt->refresh()->answered_count)->toBe(0);
});

it('records a replayed batch exactly once', function (): void {
    // The offline outbox again: the same batch may arrive many times.
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    $ids = array_slice($attempt->question_order, 0, 3);
    $uuid = (string) Str::uuid7();
    $payload = batch([[$ids[0], OptionKey::A], [$ids[1], OptionKey::B], [$ids[2], OptionKey::C]]);

    $first = ($this->sync)($attempt, $payload, $uuid);

    foreach (range(1, 10) as $ignored) {
        $replay = ($this->sync)($attempt->refresh(), $payload, $uuid);
        expect($replay->replayed)->toBeTrue()
            ->and($replay->answeredCount)->toBe($first->answeredCount);
    }

    expect(LevelTestAnswer::query()->count())->toBe(3)
        ->and($attempt->refresh()->answered_count)->toBe(3);
});

it('rejects a question that is not on this paper', function (): void {
    // Guards against answering another student's paper, or padding your own.
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    $foreign = Question::factory()->forLevel($this->scenario->level())->create();

    $result = ($this->sync)($attempt, batch([
        [$attempt->question_order[0], OptionKey::A],
        [$foreign->getKey(), OptionKey::A],
    ]), (string) Str::uuid7());

    expect($result->accepted)->toBe(1)
        ->and($result->rejected)->toBe(1)
        ->and($result->rejectedQuestionIds)->toBe([$foreign->getKey()])
        ->and(LevelTestAnswer::query()->where('question_id', $foreign->getKey())->exists())->toBeFalse();
});

it('caps how much one batch may carry', function (): void {
    // A 3,000-answer request would be a cheap way to hurt the database.
    $this->scenario = QuizScenario::realistic();
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    $pairs = array_map(
        static fn (int $id): array => [$id, OptionKey::A],
        array_slice($attempt->question_order, 0, 100),
    );

    $result = ($this->sync)($attempt, batch($pairs), (string) Str::uuid7());

    expect($result->accepted)->toBe((int) config('quiz.test.batch_max'));
});

it('collapses a question repeated inside one batch', function (): void {
    // MySQL rejects an upsert naming the same key twice; the last value wins.
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    $questionId = $attempt->question_order[0];

    $result = ($this->sync)($attempt, batch([
        [$questionId, OptionKey::A],
        [$questionId, OptionKey::D],
    ]), (string) Str::uuid7());

    expect($result->accepted)->toBe(1)
        ->and(LevelTestAnswer::query()->where('question_id', $questionId)->firstOrFail()->selected_option)
        ->toBe(OptionKey::D);
});

it('tracks flags for the question navigator', function (): void {
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    $result = ($this->sync)(
        $attempt,
        batch([[$attempt->question_order[0], null], [$attempt->question_order[1], OptionKey::A]], flagged: true),
        (string) Str::uuid7(),
    );

    expect($result->flaggedCount)->toBe(2)
        ->and($result->answeredCount)->toBe(1);
});

// ------------------------------------------------------------------ grading ----

it('grades the paper server-side', function (): void {
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    // 8 of 12 right, 3 wrong, 1 left blank.
    answerPaper($attempt, fn (int $i): ?bool => match (true) {
        $i < 8 => true,
        $i < 11 => false,
        default => null,
    });

    $graded = ($this->submitTest)($attempt->refresh());

    expect($graded->status)->toBe(TestAttemptStatus::Graded)
        ->and($graded->correct_count)->toBe(8)
        ->and($graded->incorrect_count)->toBe(3)
        ->and($graded->unanswered_count)->toBe(1)
        ->and((float) $graded->percentage)->toBe(66.67)
        ->and($graded->passed)->toBeTrue();
});

it('never awards negative marks', function (): void {
    // The student asked for this explicitly. A blank paper scores zero, not below it.
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    answerPaper($attempt, fn (int $i): bool => false);

    $graded = ($this->submitTest)($attempt->refresh());

    expect($graded->correct_count)->toBe(0)
        ->and((float) $graded->score)->toBe(0.0)
        ->and((float) $graded->percentage)->toBe(0.0)
        ->and($graded->passed)->toBeFalse()
        ->and(LevelTestAnswer::query()->where('marks_awarded', '<', 0)->exists())->toBeFalse();
});

it('counts a question never visited as unanswered', function (): void {
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    // Only two of twelve answered; the rest have no row at all.
    ($this->sync)($attempt, batch([
        [$attempt->question_order[0], OptionKey::A],
        [$attempt->question_order[1], OptionKey::B],
    ]), (string) Str::uuid7());

    $graded = ($this->submitTest)($attempt->refresh());

    expect($graded->unanswered_count)->toBe(10)
        ->and($graded->correct_count + $graded->incorrect_count)->toBe(2);
});

it('grades in a constant number of statements whatever the paper size', function (): void {
    // The property that decides whether 500 students can finish on the same evening.
    $small = QuizScenario::make(days: 2, perDay: 3);
    app(AssignLevelQuestionsAction::class)($small->enrolment);
    $small->completeAllDays();
    $smallAttempt = ($this->start)($small->enrolment->refresh());
    answerPaper($smallAttempt, fn (int $i): bool => true);

    DB::flushQueryLog();
    DB::enableQueryLog();
    ($this->submitTest)($smallAttempt->refresh());
    $smallCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $big = QuizScenario::make(days: 20, perDay: 5);   // 100 questions
    app(AssignLevelQuestionsAction::class)($big->enrolment);
    $big->completeAllDays();
    $bigAttempt = ($this->start)($big->enrolment->refresh());
    answerPaper($bigAttempt, fn (int $i): bool => true);

    // Flushed, because getQueryLog() accumulates from the first enable onwards and
    // would otherwise report the small paper's queries again as part of the big one.
    DB::flushQueryLog();
    DB::enableQueryLog();
    ($this->submitTest)($bigAttempt->refresh());
    $bigCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 6 questions vs 100 — a per-question implementation would be ~16x here.
    expect($bigCount)->toBe($smallCount, "small={$smallCount} big={$bigCount}");
});

it('is idempotent when submitted twice', function (): void {
    Event::fake([LevelTestGraded::class]);
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    answerPaper($attempt, fn (int $i): bool => true);

    $first = ($this->submitTest)($attempt->refresh());
    $stamp = $first->graded_at;

    $this->travel(1)->hours();
    $second = ($this->submitTest)($attempt->refresh());

    expect($second->graded_at->equalTo($stamp))->toBeTrue()
        ->and($second->correct_count)->toBe($first->correct_count);

    Event::assertDispatchedTimes(LevelTestGraded::class, 1);
});

it('refuses answers once the paper is submitted', function (): void {
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    ($this->submitTest)($attempt);

    expect(fn () => ($this->sync)(
        $attempt->refresh(),
        batch([[$attempt->question_order[0], OptionKey::A]]),
        (string) Str::uuid7(),
    ))->toThrow(TestAttemptClosedException::class);
});

// --------------------------------------------------------------- the clock ----

it('sets a server-side deadline when the level has a time limit', function (): void {
    $this->scenario->levelTest()->forceFill(['time_limit_minutes' => 30])->save();
    $this->scenario->completeAllDays();

    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    expect($attempt->time_limit_seconds)->toBe(1800)
        ->and($attempt->expires_at)->not->toBeNull()
        ->and($attempt->expires_at->diffInSeconds(now(), absolute: true))->toBeLessThan(1810);
});

it('accepts an answer that arrives just inside the grace window', function (): void {
    // Typed at 29:58, left the phone at 30:03. Rejecting it would be cruel and wrong.
    $this->scenario->levelTest()->forceFill(['time_limit_minutes' => 30])->save();
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    $this->travel(30)->minutes();
    $this->travel(5)->seconds();

    $result = ($this->sync)(
        $attempt->refresh(),
        batch([[$attempt->question_order[0], OptionKey::A]]),
        (string) Str::uuid7(),
    );

    expect($result->accepted)->toBe(1);
});

it('closes and grades the paper when the clock runs out', function (): void {
    $this->scenario->levelTest()->forceFill(['time_limit_minutes' => 30])->save();
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    ($this->sync)($attempt, batch([
        [$attempt->question_order[0], Question::query()->findOrFail($attempt->question_order[0])->correct_option],
    ]), (string) Str::uuid7());

    $this->travel(45)->minutes();

    expect(fn () => ($this->sync)(
        $attempt->refresh(),
        batch([[$attempt->question_order[1], OptionKey::A]]),
        (string) Str::uuid7(),
    ))->toThrow(TestAttemptExpiredException::class);

    // Graded on what arrived in time, rather than discarded.
    $closed = $attempt->refresh();
    expect($closed->status)->toBe(TestAttemptStatus::Graded)
        ->and($closed->correct_count)->toBe(1)
        ->and($closed->submitted_at)->not->toBeNull();
});

it('caps recorded time at the allowance', function (): void {
    $this->scenario->levelTest()->forceFill(['time_limit_minutes' => 30])->save();
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());

    $this->travel(3)->days();   // laptop shut, browser reopened
    $graded = ($this->submitTest)($attempt->refresh());

    expect($graded->time_spent_seconds)
        ->toBe(1800 + (int) config('quiz.test.grace_seconds'));
});

it('lets a student start a fresh paper after theirs expired', function (): void {
    $this->scenario->levelTest()->forceFill(['time_limit_minutes' => 30])->save();
    $this->scenario->completeAllDays();
    $expired = ($this->start)($this->scenario->enrolment->refresh());

    $this->travel(2)->hours();
    $fresh = ($this->start)($this->scenario->enrolment->refresh());

    expect($fresh->getKey())->not->toBe($expired->getKey())
        ->and($fresh->attempt_number)->toBe(2)
        ->and($expired->refresh()->status)->toBe(TestAttemptStatus::Graded);
});

// ---------------------------------------------------------------- progression ----

it('stamps the pass on the enrolment', function (): void {
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    answerPaper($attempt, fn (int $i): bool => true);

    ($this->submitTest)($attempt->refresh());

    expect($this->scenario->enrolment->refresh()->test_passed_at)->not->toBeNull();
});

it('never revokes a pass because of a later practice retake', function (): void {
    // Retakes are unlimited and used for practice; a bad one must not undo the pass.
    $this->scenario->completeAllDays();
    $passing = ($this->start)($this->scenario->enrolment->refresh());
    answerPaper($passing, fn (int $i): bool => true);
    ($this->submitTest)($passing->refresh());
    $passedAt = $this->scenario->enrolment->refresh()->test_passed_at;

    $this->travel(1)->days();
    $failing = ($this->start)($this->scenario->enrolment->refresh());
    answerPaper($failing, fn (int $i): bool => false);
    $graded = ($this->submitTest)($failing->refresh());

    expect($graded->passed)->toBeFalse()
        ->and($this->scenario->enrolment->refresh()->test_passed_at->equalTo($passedAt))->toBeTrue();
});

it('withholds the result when the level does not release it immediately', function (): void {
    $this->scenario->level()->forceFill(['release_results_immediately' => false])->save();
    $this->scenario->completeAllDays();
    $attempt = ($this->start)($this->scenario->enrolment->refresh());
    answerPaper($attempt, fn (int $i): bool => true);

    $graded = ($this->submitTest)($attempt->refresh());

    // Graded, but the student is not shown it yet.
    expect($graded->status)->toBe(TestAttemptStatus::Graded)
        ->and($graded->correct_count)->toBe(12)
        ->and($graded->result_released_at)->toBeNull()
        ->and($graded->resultsAreReleased())->toBeFalse();
});

it('respects an attempt limit when one is set', function (): void {
    $this->scenario->levelTest()->forceFill(['attempt_limit' => 1])->save();
    $this->scenario->completeAllDays();

    $first = ($this->start)($this->scenario->enrolment->refresh());
    ($this->submitTest)($first);

    expect(fn () => ($this->start)($this->scenario->enrolment->refresh()))
        ->toThrow(LevelTestNotEligibleException::class);
});
