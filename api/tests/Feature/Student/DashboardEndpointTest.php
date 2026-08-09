<?php

declare(strict_types=1);

use App\Actions\LevelTest\StartLevelTestAction;
use App\Actions\LevelTest\SubmitLevelTestAction;
use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\Actions\Quiz\CompleteQuizAction;
use App\Actions\Quiz\StartQuizAttemptAction;
use App\Actions\Quiz\SubmitAnswerAction;
use App\DTOs\Quiz\AnswerSubmissionData;
use App\Enums\OptionKey;
use App\Models\Question;
use App\Models\QuizAttempt;
use Illuminate\Support\Str;
use Tests\Support\QuizScenario;

/**
 * The home screen, progress and mistake review.
 *
 * The dashboard's job is to answer "what do I do next" so the client needs no logic of
 * its own — these tests pin the `next_action` contract that the big button reads.
 */
beforeEach(function (): void {
    $this->scenario = QuizScenario::make(days: 3, perDay: 3);
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
    actingAsStudent($this->scenario->student);
});

function answerAll(QuizAttempt $attempt, bool $correctly = true): void
{
    foreach ($attempt->questions()->orderBy('position')->pluck('question_id') as $questionId) {
        $question = Question::query()->findOrFail($questionId);
        $option = $correctly
            ? $question->correct_option
            : collect(OptionKey::cases())->first(fn (OptionKey $k): bool => $k !== $question->correct_option
                && in_array($k->value, ['a', 'b', 'c', 'd'], true));

        app(SubmitAnswerAction::class)($attempt->refresh(), new AnswerSubmissionData(
            questionId: $questionId,
            selectedOption: $option,
            clientAnswerUuid: (string) Str::uuid7(),
            timeSpentMs: 2_000,
            answeredAt: null,
        ));
    }
}

// ------------------------------------------------------------------ next action ----

it('tells a brand new student to start day 1', function (): void {
    $this->getJson('/api/v1/student/dashboard')
        ->assertOk()
        ->assertJsonPath('data.next_action.type', 'start_day')
        ->assertJsonPath('data.next_action.day', 1)
        ->assertJsonPath('data.level.completed_days', 0)
        ->assertJsonPath('data.streak.current', 0);
});

it('tells a student mid-day to resume', function (): void {
    $attempt = app(StartQuizAttemptAction::class)($this->scenario->enrolment, 1);

    $this->getJson('/api/v1/student/dashboard')
        ->assertOk()
        ->assertJsonPath('data.next_action.type', 'resume_day')
        ->assertJsonPath('data.next_action.attempt_uuid', $attempt->uuid);
});

it('moves the student on to the next day', function (): void {
    $attempt = app(StartQuizAttemptAction::class)($this->scenario->enrolment, 1);
    answerAll($attempt);
    app(CompleteQuizAction::class)($attempt->refresh());

    $this->getJson('/api/v1/student/dashboard')
        ->assertOk()
        ->assertJsonPath('data.next_action.type', 'start_day')
        ->assertJsonPath('data.next_action.day', 2)
        ->assertJsonPath('data.level.completed_days', 1)
        ->assertJsonPath('data.streak.current', 1);
});

it('points at the month-end test when every day is done', function (): void {
    $this->scenario->completeAllDays();

    $this->getJson('/api/v1/student/dashboard')
        ->assertOk()
        ->assertJsonPath('data.next_action.type', 'take_level_test')
        ->assertJsonPath('data.level_test.unlocked', true);
});

it('offers the next level once the test is sat', function (): void {
    $this->scenario->completeAllDays();
    $attempt = app(StartLevelTestAction::class)($this->scenario->enrolment->refresh());
    app(SubmitLevelTestAction::class)($attempt);

    // Only one level in this programme, so the next step is cycle 2.
    $this->getJson('/api/v1/student/dashboard')
        ->assertOk()
        ->assertJsonPath('data.next_action.type', 'advance_level')
        ->assertJsonPath('data.next_action.next_cycle', 2);
});

it('reports the programme as finished when there is nowhere left to go', function (): void {
    $this->scenario->programme->forceFill(['total_cycles' => 1])->save();
    $this->scenario->completeAllDays();
    $attempt = app(StartLevelTestAction::class)($this->scenario->enrolment->refresh());
    app(SubmitLevelTestAction::class)($attempt);

    $this->getJson('/api/v1/student/dashboard')
        ->assertOk()
        ->assertJsonPath('data.next_action.type', 'programme_complete');
});

it('describes the programme and level the student is on', function (): void {
    $response = $this->getJson('/api/v1/student/dashboard');

    $response->assertOk()
        ->assertJsonPath('data.programme.title', $this->scenario->programme->title)
        ->assertJsonPath('data.programme.exam', $this->scenario->category->title)
        ->assertJsonPath('data.programme.current_level', 1)
        ->assertJsonPath('data.level.total_days', 3)
        ->assertJsonPath('data.level.questions_per_day', 3);
});

it('leaks no answers on the dashboard', function (): void {
    $body = $this->getJson('/api/v1/student/dashboard')->getContent();

    expect($body)
        ->not->toContain('correct_option')
        ->not->toContain('explanation');
});

// -------------------------------------------------------------------- progress ----

it('reports first-attempt accuracy, not just submission accuracy', function (): void {
    // A student who eventually gets everything right has 100% submission accuracy in a
    // world with unlimited retries. First-attempt accuracy is the honest number.
    $attempt = app(StartQuizAttemptAction::class)($this->scenario->enrolment, 1);
    answerAll($attempt, correctly: false);
    answerAll($attempt, correctly: true);

    $response = $this->getJson('/api/v1/student/progress');

    $response->assertOk()
        ->assertJsonPath('data.accuracy.questions_seen', 3)
        ->assertJsonPath('data.accuracy.questions_mastered', 3)
        ->assertJsonPath('data.accuracy.total_submissions', 6);

    // Compared numerically: a whole-number percentage serialises as 50, not 50.0,
    // so a strict path assertion would be testing JSON encoding rather than the maths.
    expect($response->json('data.accuracy.submission_accuracy_percent'))->toEqual(50)
        ->and($response->json('data.accuracy.first_attempt_accuracy_percent'))->toEqual(0);
});

it('explains that retention needs a second cycle before it means anything', function (): void {
    $this->getJson('/api/v1/student/progress')
        ->assertOk()
        ->assertJsonPath('data.retention.available', false);
});

it('breaks accuracy down by subject', function (): void {
    $attempt = app(StartQuizAttemptAction::class)($this->scenario->enrolment, 1);
    answerAll($attempt, correctly: false);

    $response = $this->getJson('/api/v1/student/progress');

    $response->assertOk();

    expect($response->json('data.by_topic'))->not->toBeEmpty()
        ->and($response->json('data.by_topic.0'))->toHaveKeys(['topic', 'seen', 'mastered', 'wrong_answers']);
});

// -------------------------------------------------------------------- mistakes ----

it('lists nothing before the student gets anything wrong', function (): void {
    // The guard that stops this endpoint being a complete answer key.
    $this->getJson('/api/v1/student/mistakes')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('lists a wrong question with its answer and explanation', function (): void {
    // Safe: the student was already shown this answer at the moment they got it wrong.
    $attempt = app(StartQuizAttemptAction::class)($this->scenario->enrolment, 1);
    answerAll($attempt, correctly: false);

    $response = $this->getJson('/api/v1/student/mistakes');

    $response->assertOk()->assertJsonCount(3, 'data');

    $first = $response->json('data.0');
    $question = Question::query()->findOrFail($first['question_id']);

    expect($first['correct_option'])->toBe($question->correct_option->value)
        ->and($first['explanation'])->toBe($question->explanation)
        ->and($first['your_history']['wrong_count'])->toBe(1)
        ->and($first['your_history']['is_mastered'])->toBeFalse()
        ->and(collect($first['options'])->firstWhere('is_correct', true))->not->toBeNull();
});

it('can narrow the list to what is still unmastered', function (): void {
    $attempt = app(StartQuizAttemptAction::class)($this->scenario->enrolment, 1);
    answerAll($attempt, correctly: false);
    answerAll($attempt, correctly: true);

    $this->getJson('/api/v1/student/mistakes')->assertOk()->assertJsonCount(3, 'data');
    $this->getJson('/api/v1/student/mistakes?only_unmastered=1')->assertOk()->assertJsonCount(0, 'data');
});

it('shows one student nothing of another student mistakes', function (): void {
    $attempt = app(StartQuizAttemptAction::class)($this->scenario->enrolment, 1);
    answerAll($attempt, correctly: false);

    $other = QuizScenario::make(days: 3, perDay: 3);
    app(AssignLevelQuestionsAction::class)($other->enrolment);
    app()['auth']->forgetGuards();
    actingAsStudent($other->student);

    $this->getJson('/api/v1/student/mistakes')->assertOk()->assertJsonCount(0, 'data');
});
