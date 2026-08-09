<?php

declare(strict_types=1);

use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\Actions\Quiz\SubmitAnswerAction;
use App\DTOs\Quiz\AnswerSubmissionData;
use App\Enums\AttemptStatus;
use App\Enums\OptionKey;
use App\Enums\QuestionState;
use App\Enums\RetryMode;
use App\Exceptions\AttemptAlreadyCompletedException;
use App\Models\Question;
use App\Models\QuizAttemptAnswer;
use App\Models\StudentQuestionProgress;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Tests\Support\QuizScenario;

/**
 * Business rules 4-9, 19-22: server-side evaluation, retry, and the guarantee that a
 * replayed offline answer is recorded exactly once.
 */
beforeEach(function (): void {
    $this->submit = app(SubmitAnswerAction::class);
    $this->scenario = QuizScenario::make(days: 2, perDay: 3);
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
    $this->attempt = $this->scenario->startAttempt(1);
});

/** @return array{0: Question, 1: OptionKey} the question and its correct option */
function firstQuestion($attempt): array
{
    $question = Question::query()->findOrFail(
        $attempt->questions()->orderBy('position')->value('question_id'),
    );

    return [$question, $question->correct_option];
}

function wrongOptionFor(Question $question): OptionKey
{
    return collect(OptionKey::cases())
        ->filter(fn (OptionKey $k): bool => in_array($k->value, ['a', 'b', 'c', 'd'], true))
        ->first(fn (OptionKey $k): bool => $k !== $question->correct_option);
}

function submission(Question $question, OptionKey $option, ?string $uuid = null): AnswerSubmissionData
{
    return new AnswerSubmissionData(
        questionId: $question->getKey(),
        selectedOption: $option,
        clientAnswerUuid: $uuid ?? (string) Str::uuid7(),
        timeSpentMs: 4200,
        answeredAt: null,
    );
}

// ------------------------------------------------------- correct answers ----

it('marks a correct answer as mastered', function (): void {
    [$question, $correct] = firstQuestion($this->attempt);

    $result = ($this->submit)($this->attempt, submission($question, $correct));

    expect($result->isCorrect)->toBeTrue()
        ->and($result->retryRequired)->toBeFalse()
        ->and($result->masteredCount)->toBe(1)
        ->and($result->requiredCount)->toBe(3)
        ->and($result->canComplete)->toBeFalse();

    expect($this->attempt->questions()->where('question_id', $question->getKey())->first()->state)
        ->toBe(QuestionState::Mastered);
});

it('hides the explanation on a correct answer by default', function (): void {
    [$question, $correct] = firstQuestion($this->attempt);

    $result = ($this->submit)($this->attempt, submission($question, $correct));

    // Nothing to teach — the student already knew it.
    expect($result->correctOption)->toBeNull()
        ->and($result->explanation)->toBeNull();
});

it('reveals the explanation on a correct answer when the level opts in', function (): void {
    $this->scenario->level()->forceFill(['show_explanation_on_correct' => true])->save();
    [$question, $correct] = firstQuestion($this->attempt->refresh());

    $result = ($this->submit)($this->attempt, submission($question, $correct));

    expect($result->explanation)->toBe($question->explanation);
});

// --------------------------------------------------------- wrong answers ----

it('reveals the correct answer and explanation on a wrong answer', function (): void {
    // Business rules 5 and 6 — this is the teaching moment.
    [$question] = firstQuestion($this->attempt);

    $result = ($this->submit)($this->attempt, submission($question, wrongOptionFor($question)));

    expect($result->isCorrect)->toBeFalse()
        ->and($result->retryRequired)->toBeTrue()
        ->and($result->correctOption)->toBe($question->correct_option->value)
        ->and($result->correctAnswerText)->toBe($question->correct_answer_text)
        ->and($result->explanation)->toBe($question->explanation)
        ->and($result->masteredCount)->toBe(0);
});

it('puts a wrong question into the retry set', function (): void {
    [$question] = firstQuestion($this->attempt);

    $result = ($this->submit)($this->attempt, submission($question, wrongOptionFor($question)));

    expect($result->retryRequiredQuestionIds)->toBe([$question->getKey()])
        ->and($this->attempt->questions()->where('question_id', $question->getKey())->first()->state)
        ->toBe(QuestionState::RetryRequired);
});

it('allows unlimited retries until the answer is right', function (): void {
    [$question, $correct] = firstQuestion($this->attempt);
    $wrong = wrongOptionFor($question);

    foreach (range(1, 4) as $attemptNumber) {
        $result = ($this->submit)($this->attempt->refresh(), submission($question, $wrong));
        expect($result->submissionNumber)->toBe($attemptNumber);
    }

    $result = ($this->submit)($this->attempt->refresh(), submission($question, $correct));

    expect($result->isCorrect)->toBeTrue()
        ->and($result->submissionNumber)->toBe(5)
        ->and($result->masteredCount)->toBe(1);
});

it('records every submission including retries', function (): void {
    // Business rule 19: the log is append-only, nothing is overwritten.
    [$question, $correct] = firstQuestion($this->attempt);
    $wrong = wrongOptionFor($question);

    ($this->submit)($this->attempt, submission($question, $wrong));
    ($this->submit)($this->attempt->refresh(), submission($question, $wrong));
    ($this->submit)($this->attempt->refresh(), submission($question, $correct));

    $log = QuizAttemptAnswer::query()
        ->where('quiz_attempt_id', $this->attempt->getKey())
        ->orderBy('submission_number')
        ->get();

    expect($log)->toHaveCount(3)
        ->and($log->pluck('is_correct')->all())->toBe([false, false, true])
        ->and($log->pluck('submission_number')->all())->toBe([1, 2, 3]);
});

it('never un-masters a question that was already correct', function (): void {
    [$question, $correct] = firstQuestion($this->attempt);

    ($this->submit)($this->attempt, submission($question, $correct));
    $result = ($this->submit)($this->attempt->refresh(), submission($question, wrongOptionFor($question)));

    // The wrong retry is recorded, but mastery is not revoked and the count holds.
    expect($result->masteredCount)->toBe(1)
        ->and($this->attempt->refresh()->mastered_count)->toBe(1);
});

// ------------------------------------------------------------ the client ----

it('ignores any correctness the client tries to assert', function (): void {
    [$question] = firstQuestion($this->attempt);
    $wrong = wrongOptionFor($question);

    // AnswerSubmissionData has nowhere to put is_correct/score, by design.
    $data = AnswerSubmissionData::fromArray([
        'question_id' => $question->getKey(),
        'selected_option' => $wrong->value,
        'client_answer_uuid' => (string) Str::uuid7(),
        'is_correct' => true,
        'score' => 10,
        'mastered' => true,
    ]);

    $result = ($this->submit)($this->attempt, $data);

    expect($result->isCorrect)->toBeFalse()
        ->and($result->masteredCount)->toBe(0);
});

it('clamps an implausible client timestamp', function (): void {
    [$question, $correct] = firstQuestion($this->attempt);

    $data = new AnswerSubmissionData(
        questionId: $question->getKey(),
        selectedOption: $correct,
        clientAnswerUuid: (string) Str::uuid7(),
        timeSpentMs: 999_999_999,
        answeredAt: CarbonImmutable::now()->addYear(),  // device clock is wrong
    );

    ($this->submit)($this->attempt, $data);

    $recorded = QuizAttemptAnswer::query()->latest('id')->firstOrFail();

    expect($recorded->answered_at->isFuture())->toBeFalse()
        // An hour is the cap; 999999999 ms would otherwise poison study-time totals.
        ->and($this->attempt->refresh()->time_spent_seconds)->toBe(3600);
});

it('rejects a question that does not belong to the attempt', function (): void {
    // Guards against submitting answers to another day's questions.
    $foreign = Question::factory()->forLevel($this->scenario->level())->create();

    expect(fn () => ($this->submit)($this->attempt, submission($foreign, OptionKey::A)))
        ->toThrow(ModelNotFoundException::class);
});

it('refuses to accept answers into a completed attempt', function (): void {
    [$question, $correct] = firstQuestion($this->attempt);
    $this->attempt->forceFill(['status' => AttemptStatus::Completed])->save();

    expect(fn () => ($this->submit)($this->attempt, submission($question, $correct)))
        ->toThrow(AttemptAlreadyCompletedException::class);
});

// ------------------------------------------------------------ idempotency ----

it('records a replayed answer exactly once', function (): void {
    // The guarantee the whole offline outbox depends on (business rules 21, 22).
    [$question, $correct] = firstQuestion($this->attempt);
    $uuid = (string) Str::uuid7();

    $first = ($this->submit)($this->attempt, submission($question, $correct, $uuid));
    $replay = ($this->submit)($this->attempt->refresh(), submission($question, $correct, $uuid));

    expect($replay->replayed)->toBeTrue()
        ->and($replay->isCorrect)->toBe($first->isCorrect)
        ->and($replay->submissionNumber)->toBe($first->submissionNumber);

    expect(QuizAttemptAnswer::query()->where('quiz_attempt_id', $this->attempt->getKey())->count())->toBe(1)
        ->and($this->attempt->refresh()->mastered_count)->toBe(1)
        ->and($this->attempt->correct_submissions)->toBe(1);
});

it('survives a 20-fold replay storm without double counting', function (): void {
    // Simulates an outbox draining the same answer after a reinstall.
    [$question, $correct] = firstQuestion($this->attempt);
    $uuid = (string) Str::uuid7();

    foreach (range(1, 20) as $ignored) {
        ($this->submit)($this->attempt->refresh(), submission($question, $correct, $uuid));
    }

    expect(QuizAttemptAnswer::query()->where('quiz_attempt_id', $this->attempt->getKey())->count())->toBe(1)
        ->and($this->attempt->refresh()->mastered_count)->toBe(1)
        ->and(StudentQuestionProgress::query()
            ->where('user_id', $this->scenario->student->getKey())
            ->where('question_id', $question->getKey())
            ->first()->attempts)->toBe(1);
});

// ----------------------------------------------------------- retry timing ----

it('requeues a wrong question behind the others by default', function (): void {
    // docs/adr/003 fix 2: a gap between seeing the answer and being asked again is
    // what turns recognition into recall.
    expect($this->scenario->level()->retry_mode)->toBe(RetryMode::RequeueAtEnd);

    $questionIds = $this->attempt->questions()->orderBy('position')->pluck('question_id')->all();
    $first = Question::query()->findOrFail($questionIds[0]);

    $result = ($this->submit)($this->attempt, submission($first, wrongOptionFor($first)));

    expect($result->nextQuestionId)->toBe($questionIds[1])
        ->and($result->nextQuestionId)->not->toBe($first->getKey());
});

it('serves the same question again in immediate retry mode', function (): void {
    $this->scenario->level()->forceFill(['retry_mode' => RetryMode::Immediate])->save();
    [$question] = firstQuestion($this->attempt->refresh());

    $result = ($this->submit)($this->attempt, submission($question, wrongOptionFor($question)));

    expect($result->nextQuestionId)->toBe($question->getKey());
});

// -------------------------------------------------------- progress record ----

it('accumulates lifetime progress per question', function (): void {
    [$question, $correct] = firstQuestion($this->attempt);

    ($this->submit)($this->attempt, submission($question, wrongOptionFor($question)));
    ($this->submit)($this->attempt->refresh(), submission($question, $correct));

    $progress = StudentQuestionProgress::query()
        ->where('user_id', $this->scenario->student->getKey())
        ->where('question_id', $question->getKey())
        ->firstOrFail();

    expect($progress->attempts)->toBe(2)
        ->and($progress->wrong_count)->toBe(1)
        ->and($progress->correct_count)->toBe(1)
        ->and($progress->is_mastered)->toBeTrue()
        ->and($progress->first_attempt_correct)->toBeFalse()
        // The retention signal: got it wrong first time in cycle 1.
        ->and($progress->firstAttemptCorrectInCycle(1))->toBeFalse();
});

it('records first-attempt accuracy per cycle for the retention metric', function (): void {
    [$question, $correct] = firstQuestion($this->attempt);
    ($this->submit)($this->attempt, submission($question, wrongOptionFor($question)));

    // Cycle 2: the same question comes round again and the student now gets it right.
    $cycleTwo = $this->scenario->enrolInLevel(1, cycle: 2);
    app(AssignLevelQuestionsAction::class)($cycleTwo);
    $secondAttempt = $this->scenario->startAttempt(1, $cycleTwo);

    $inCycleTwo = Question::query()->findOrFail(
        $secondAttempt->questions()->orderBy('position')->value('question_id'),
    );
    ($this->submit)($secondAttempt, submission($inCycleTwo, $inCycleTwo->correct_option));

    $progress = StudentQuestionProgress::query()
        ->where('user_id', $this->scenario->student->getKey())
        ->where('question_id', $inCycleTwo->getKey())
        ->firstOrFail();

    expect($progress->last_cycle_seen)->toBe(2)
        ->and($progress->firstAttemptCorrectInCycle(2))->toBeTrue();
});

// ------------------------------------------------------------ query budget ----

it('submits an answer in a small, constant number of statements', function (): void {
    [$question, $correct] = firstQuestion($this->attempt);

    DB::enableQueryLog();
    ($this->submit)($this->attempt, submission($question, $correct));
    $queries = collect(DB::getQueryLog())
        ->reject(fn (array $q): bool => str_starts_with(strtolower(trim($q['query'])), 'savepoint'))
        ->reject(fn (array $q): bool => str_contains(strtolower($q['query']), 'release'))
        ->count();
    DB::disableQueryLog();

    // Phase 1 budget was 8 statements. Anything much above means a join or an
    // aggregate has crept onto the hottest write path.
    expect($queries)->toBeLessThanOrEqual(12, "submission used {$queries} statements");
});
