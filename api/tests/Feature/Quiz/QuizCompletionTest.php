<?php

declare(strict_types=1);

use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\Actions\Quiz\CompleteQuizAction;
use App\Actions\Quiz\SubmitAnswerAction;
use App\DTOs\Quiz\AnswerSubmissionData;
use App\Enums\AttemptStatus;
use App\Enums\OptionKey;
use App\Enums\QuestionState;
use App\Events\DailyQuizCompleted;
use App\Events\LevelTestUnlocked;
use App\Exceptions\QuizNotCompleteException;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\StudentStreak;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\QuizScenario;

/**
 * Business rules 8, 9, 10 and 23: a day is only "done" at full marks, the check is
 * re-derived server-side, and a retried /complete is a success rather than a conflict.
 */
beforeEach(function (): void {
    $this->submit = app(SubmitAnswerAction::class);
    $this->complete = app(CompleteQuizAction::class);
    $this->scenario = QuizScenario::make(days: 2, perDay: 3);
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
});

/** Answer every question in the attempt correctly. */
function masterAll(QuizAttempt $attempt, int $limit = PHP_INT_MAX): void
{
    $ids = $attempt->questions()->orderBy('position')->pluck('question_id')->all();

    foreach (array_slice($ids, 0, min($limit, count($ids))) as $questionId) {
        $question = Question::query()->findOrFail($questionId);

        app(SubmitAnswerAction::class)($attempt->refresh(), new AnswerSubmissionData(
            questionId: $question->getKey(),
            selectedOption: $question->correct_option,
            clientAnswerUuid: (string) Str::uuid7(),
            timeSpentMs: 5_000,
            answeredAt: null,
        ));
    }
}

// ------------------------------------------------------------ the 10/10 gate ----

it('completes a day once every question is mastered', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);

    $result = ($this->complete)($attempt->refresh());

    expect($result->dayNumber)->toBe(1)
        ->and($result->score)->toBe(3)
        ->and($result->requiredCount)->toBe(3)
        ->and($result->alreadyCompleted)->toBeFalse()
        ->and($attempt->refresh()->status)->toBe(AttemptStatus::Completed)
        ->and($attempt->completed_at)->not->toBeNull();
});

it('refuses to complete a partially answered day', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt, limit: 2);

    expect(fn () => ($this->complete)($attempt->refresh()))
        ->toThrow(QuizNotCompleteException::class);

    expect($attempt->refresh()->status)->toBe(AttemptStatus::InProgress)
        ->and($attempt->completed_at)->toBeNull();
});

it('names the outstanding questions so the client can jump straight to them', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt, limit: 2);

    $outstanding = $attempt->refresh()->questions()
        ->where('state', '!=', QuestionState::Mastered->value)
        ->pluck('question_id')
        ->all();

    try {
        ($this->complete)($attempt->refresh());
        $this->fail('completion should have been rejected');
    } catch (QuizNotCompleteException $e) {
        expect($e->status())->toBe(422)
            ->and($e->errorCode())->toBe('QUIZ_NOT_COMPLETE')
            ->and($e->meta()['mastered_count'])->toBe(2)
            ->and($e->meta()['required_count'])->toBe(3)
            ->and($e->meta()['outstanding_question_ids'])->toBe($outstanding);
    }
});

it('re-derives the score instead of trusting the cached counter', function (): void {
    // The attempt-level counter is a convenience for dashboards. If it ever drifts —
    // or is tampered with — completion must still fail, because the per-question
    // state table is the authority.
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt, limit: 1);
    $attempt->refresh()->forceFill(['mastered_count' => 3, 'score' => 3])->save();

    expect(fn () => ($this->complete)($attempt->refresh()))
        ->toThrow(QuizNotCompleteException::class);
});

it('rewrites the score from the state table on success', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);
    $attempt->refresh()->forceFill(['mastered_count' => 99, 'score' => 99])->save();

    $result = ($this->complete)($attempt->refresh());

    expect($result->score)->toBe(3)
        ->and($attempt->refresh()->score)->toBe(3)
        ->and($attempt->mastered_count)->toBe(3);
});

// ------------------------------------------------------------- idempotency ----

it('treats a replayed completion as a success', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);

    $first = ($this->complete)($attempt->refresh());
    $replay = ($this->complete)($attempt->refresh());

    expect($first->alreadyCompleted)->toBeFalse()
        ->and($replay->alreadyCompleted)->toBeTrue()
        ->and($replay->score)->toBe($first->score)
        ->and($replay->completedDays)->toBe($first->completedDays);
});

it('does not inflate progress when completion is replayed', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);

    foreach (range(1, 5) as $ignored) {
        ($this->complete)($attempt->refresh());
    }

    expect($this->scenario->enrolment->refresh()->completed_days)->toBe(1)
        ->and(QuizAttempt::query()->where('status', AttemptStatus::Completed)->count())->toBe(1);
});

it('keeps the original completion timestamp on replay', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);
    ($this->complete)($attempt->refresh());
    $firstStamp = $attempt->refresh()->completed_at;

    $this->travel(2)->hours();
    ($this->complete)($attempt->refresh());

    expect($attempt->refresh()->completed_at->equalTo($firstStamp))->toBeTrue();
});

// ------------------------------------------------------------------ streaks ----

it('starts a streak on the first completed day', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);

    $result = ($this->complete)($attempt->refresh());

    expect($result->currentStreak)->toBe(1)
        ->and($result->longestStreak)->toBe(1);
});

it('extends the streak on a consecutive day', function (): void {
    $day1 = $this->scenario->startAttempt(1);
    masterAll($day1);
    ($this->complete)($day1->refresh());

    $this->travel(1)->days();

    $day2 = $this->scenario->startAttempt(2);
    masterAll($day2);
    $result = ($this->complete)($day2->refresh());

    expect($result->currentStreak)->toBe(2)
        ->and($result->longestStreak)->toBe(2);
});

it('counts two days finished in one sitting as one day of streak', function (): void {
    // Otherwise a weekend binge would report a 10-day streak for one day of study.
    $day1 = $this->scenario->startAttempt(1);
    masterAll($day1);
    ($this->complete)($day1->refresh());

    $day2 = $this->scenario->startAttempt(2);
    masterAll($day2);
    $result = ($this->complete)($day2->refresh());

    expect($result->currentStreak)->toBe(1);
});

it('does not advance the streak when completion is replayed a day later', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);
    ($this->complete)($attempt->refresh());

    // A stale client draining its outbox tomorrow must not earn a second day.
    $this->travel(1)->days();
    ($this->complete)($attempt->refresh());

    $streak = StudentStreak::query()
        ->where('user_id', $this->scenario->student->getKey())
        ->firstOrFail();

    expect($streak->current_streak)->toBe(1)
        ->and($streak->longest_streak)->toBe(1);
});

// ------------------------------------------------------- progress and unlock ----

it('advances the enrolment cache to the next day', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);

    ($this->complete)($attempt->refresh());
    $enrolment = $this->scenario->enrolment->refresh();

    expect($enrolment->completed_days)->toBe(1)
        ->and($enrolment->last_completed_day)->toBe(1)
        ->and($enrolment->current_day)->toBe(2)
        ->and($enrolment->highest_unlocked_day)->toBe(2)
        ->and((float) $enrolment->progress_percent)->toBe(50.0)
        ->and($enrolment->total_study_seconds)->toBeGreaterThan(0);
});

it('reports the next day as unlocked', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);

    $result = ($this->complete)($attempt->refresh());

    expect($result->nextDay)->toBe(2)
        ->and($result->nextDayUnlocked)->toBeTrue()
        ->and($result->nextDayHint)->toBeNull()
        ->and($result->levelTestUnlocked)->toBeFalse();
});

it('unlocks the month-end test on the final day', function (): void {
    $this->scenario->completeDay(1);

    $final = $this->scenario->startAttempt(2);
    masterAll($final);
    $result = ($this->complete)($final->refresh());

    expect($result->nextDay)->toBeNull()
        ->and($result->levelTestUnlocked)->toBeTrue()
        ->and($result->completedDays)->toBe(2)
        ->and($this->scenario->enrolment->refresh()->test_unlocked_at)->not->toBeNull();
});

it('leaves the test locked while any day is outstanding', function (): void {
    // Finishing day 2 out of order does not open the test: day 1 is still unfinished.
    $attempt = $this->scenario->startAttempt(2);
    masterAll($attempt);

    $result = ($this->complete)($attempt->refresh());

    expect($result->levelTestUnlocked)->toBeFalse()
        ->and($this->scenario->enrolment->refresh()->test_unlocked_at)->toBeNull();
});

it('stamps the test unlock time only once', function (): void {
    $this->scenario->completeDay(1);
    $final = $this->scenario->startAttempt(2);
    masterAll($final);
    ($this->complete)($final->refresh());
    $stamp = $this->scenario->enrolment->refresh()->test_unlocked_at;

    $this->travel(3)->hours();
    ($this->complete)($final->refresh());

    expect($this->scenario->enrolment->refresh()->test_unlocked_at->equalTo($stamp))->toBeTrue();
});

// ------------------------------------------------------------------- events ----

it('announces the completion after the transaction commits', function (): void {
    Event::fake([DailyQuizCompleted::class, LevelTestUnlocked::class]);

    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);
    ($this->complete)($attempt->refresh());

    Event::assertDispatched(
        DailyQuizCompleted::class,
        fn (DailyQuizCompleted $e): bool => $e->attempt->is($attempt)
            && $e->attempt->status === AttemptStatus::Completed
            && $e->levelTestUnlocked === false,
    );
    Event::assertNotDispatched(LevelTestUnlocked::class);
});

it('announces the test unlock exactly once', function (): void {
    $this->scenario->completeDay(1);
    Event::fake([DailyQuizCompleted::class, LevelTestUnlocked::class]);

    $final = $this->scenario->startAttempt(2);
    masterAll($final);
    ($this->complete)($final->refresh());
    ($this->complete)($final->refresh());   // replay

    Event::assertDispatchedTimes(DailyQuizCompleted::class, 1);
    Event::assertDispatchedTimes(LevelTestUnlocked::class, 1);
});

it('stays silent when completion is rejected', function (): void {
    Event::fake([DailyQuizCompleted::class, LevelTestUnlocked::class]);

    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt, limit: 1);

    try {
        ($this->complete)($attempt->refresh());
    } catch (QuizNotCompleteException) {
        // expected
    }

    Event::assertNothingDispatched();
});

// ------------------------------------------------------------- other students ----

it('keeps completion scoped to the student who earned it', function (): void {
    $attempt = $this->scenario->startAttempt(1);
    masterAll($attempt);
    ($this->complete)($attempt->refresh());

    $other = QuizScenario::make(days: 2, perDay: 3);
    app(AssignLevelQuestionsAction::class)($other->enrolment);

    expect($other->enrolment->refresh()->completed_days)->toBe(0)
        ->and(StudentStreak::query()->where('user_id', $other->student->getKey())->count())->toBe(0);
});

it('ignores an option key the level never offered', function (): void {
    // Belt and braces: OptionKey::E exists for five-option papers, but a four-option
    // question must not be completable by guessing outside its own option set.
    $attempt = $this->scenario->startAttempt(1);
    $question = Question::query()->findOrFail(
        $attempt->questions()->orderBy('position')->value('question_id'),
    );

    ($this->submit)($attempt, new AnswerSubmissionData(
        questionId: $question->getKey(),
        selectedOption: OptionKey::E,
        clientAnswerUuid: (string) Str::uuid7(),
        timeSpentMs: 1_000,
        answeredAt: null,
    ));

    expect($attempt->refresh()->mastered_count)->toBe(0);
});
