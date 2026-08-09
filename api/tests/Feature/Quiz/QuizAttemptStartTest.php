<?php

declare(strict_types=1);

use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\Actions\Quiz\CompleteQuizAction;
use App\Actions\Quiz\StartQuizAttemptAction;
use App\Actions\Quiz\SubmitAnswerAction;
use App\DTOs\Quiz\AnswerSubmissionData;
use App\Enums\AttemptStatus;
use App\Enums\QuestionState;
use App\Exceptions\QuizDayLockedException;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Services\Quiz\DailyQuizDeliveryService;
use Illuminate\Support\Str;
use Tests\Support\QuizScenario;

/**
 * Opening a day. The interesting case is not starting — it is resuming, because a
 * student on a phone will lose the connection or swap devices mid-quiz.
 */
beforeEach(function (): void {
    $this->startDay = app(StartQuizAttemptAction::class);
    $this->scenario = QuizScenario::make(days: 3, perDay: 3);
});

function masterAttempt(QuizAttempt $attempt): void
{
    foreach ($attempt->questions()->orderBy('position')->pluck('question_id') as $questionId) {
        $question = Question::query()->findOrFail($questionId);
        app(SubmitAnswerAction::class)($attempt->refresh(), new AnswerSubmissionData(
            questionId: $questionId,
            selectedOption: $question->correct_option,
            clientAnswerUuid: (string) Str::uuid7(),
            timeSpentMs: 1_000,
            answeredAt: null,
        ));
    }
}

it('assigns the question set on the very first visit', function (): void {
    expect($this->scenario->enrolment->questionsAreAssigned())->toBeFalse();

    $attempt = ($this->startDay)($this->scenario->enrolment, 1);

    expect($this->scenario->enrolment->refresh()->questionsAreAssigned())->toBeTrue()
        ->and($attempt->questions()->count())->toBe(3)
        ->and($attempt->required_count)->toBe(3)
        ->and($attempt->attempt_number)->toBe(1)
        ->and($attempt->status)->toBe(AttemptStatus::InProgress);
});

it('reports zero rather than null for a fresh attempt', function (): void {
    // Database defaults are not in the model until it is reloaded, and a null
    // mastered_count would reach the first payload the student ever sees.
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);

    expect($attempt->mastered_count)->toBe(0)
        ->and($attempt->correct_submissions)->toBe(0)
        ->and($attempt->wrong_submissions)->toBe(0)
        ->and($attempt->time_spent_seconds)->toBe(0);
});

it('resumes the same attempt instead of starting a second', function (): void {
    $first = ($this->startDay)($this->scenario->enrolment, 1);
    $resumed = ($this->startDay)($this->scenario->enrolment->refresh(), 1);

    expect($resumed->getKey())->toBe($first->getKey())
        ->and(QuizAttempt::query()->count())->toBe(1);
});

it('keeps progress when a student comes back', function (): void {
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);
    $question = Question::query()->findOrFail(
        $attempt->questions()->orderBy('position')->value('question_id'),
    );

    app(SubmitAnswerAction::class)($attempt, new AnswerSubmissionData(
        questionId: $question->getKey(),
        selectedOption: $question->correct_option,
        clientAnswerUuid: (string) Str::uuid7(),
        timeSpentMs: 1_000,
        answeredAt: null,
    ));

    $resumed = ($this->startDay)($this->scenario->enrolment->refresh(), 1);

    expect($resumed->mastered_count)->toBe(1)
        ->and($resumed->questions()->where('state', QuestionState::Mastered->value)->count())->toBe(1);
});

it('serves the same ten questions to the same student every time', function (): void {
    // The assignment is persisted, so a resume cannot deal a different set.
    $first = ($this->startDay)($this->scenario->enrolment, 1);
    $ids = $first->questions()->orderBy('position')->pluck('question_id')->all();

    $resumed = ($this->startDay)($this->scenario->enrolment->refresh(), 1);

    expect($resumed->questions()->orderBy('position')->pluck('question_id')->all())->toBe($ids);
});

it('refuses a day the student has not unlocked', function (): void {
    expect(fn () => ($this->startDay)($this->scenario->enrolment, 3))
        ->toThrow(QuizDayLockedException::class);

    expect(QuizAttempt::query()->count())->toBe(0);
});

it('allows an unlimited retake of a finished day', function (): void {
    // The student asked for unlimited tries, with no penalty.
    $first = ($this->startDay)($this->scenario->enrolment, 1);
    masterAttempt($first);
    app(CompleteQuizAction::class)($first->refresh());

    $retake = ($this->startDay)($this->scenario->enrolment->refresh(), 1);

    expect($retake->getKey())->not->toBe($first->getKey())
        ->and($retake->attempt_number)->toBe(2)
        ->and($retake->status)->toBe(AttemptStatus::InProgress)
        ->and($retake->mastered_count)->toBe(0);
});

it('does not un-complete a day when it is retaken', function (): void {
    // Completion is derived from the existence of a full-marks attempt, so a fresh
    // in-progress attempt cannot take the day — or the next day's unlock — away.
    $first = ($this->startDay)($this->scenario->enrolment, 1);
    masterAttempt($first);
    app(CompleteQuizAction::class)($first->refresh());

    ($this->startDay)($this->scenario->enrolment->refresh(), 1);
    $enrolment = $this->scenario->enrolment->refresh();

    expect($enrolment->completed_days)->toBe(1)
        ->and(fn () => ($this->startDay)($enrolment, 2))->not->toThrow(QuizDayLockedException::class);
});

it('gives a retake a different option order', function (): void {
    $scenario = QuizScenario::make(days: 1, perDay: 30);
    $first = ($this->startDay)($scenario->enrolment, 1);
    $delivery = app(DailyQuizDeliveryService::class);

    $before = collect($delivery->payload($first)['questions'])
        ->mapWithKeys(fn (array $q): array => [$q['question_id'] => collect($q['options'])->pluck('key')->all()]);

    masterAttempt($first);
    app(CompleteQuizAction::class)($first->refresh());
    $retake = ($this->startDay)($scenario->enrolment->refresh(), 1);

    $after = collect($delivery->payload($retake)['questions'])
        ->mapWithKeys(fn (array $q): array => [$q['question_id'] => collect($q['options'])->pluck('key')->all()]);

    $moved = $before->filter(fn (array $keys, int $id): bool => $keys !== $after->get($id));

    expect($moved->count())->toBeGreaterThan(22);
});

it('opens a day in a small number of statements', function (): void {
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);

    DB::flushQueryLog();
    DB::enableQueryLog();
    ($this->startDay)($this->scenario->enrolment->refresh(), 1);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(20, "start used {$count} statements");
});
