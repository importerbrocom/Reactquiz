<?php

declare(strict_types=1);

use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\Models\Question;
use Illuminate\Support\Str;
use Tests\Support\QuizScenario;

/**
 * The daily quiz over HTTP: the path a student's phone actually takes.
 */
beforeEach(function (): void {
    $this->scenario = QuizScenario::make(days: 3, perDay: 3);
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
    actingAsStudent($this->scenario->student);
});

function answerPayload(int $questionId, string $option): array
{
    return [
        'question_id' => $questionId,
        'selected_option' => $option,
        'client_answer_uuid' => (string) Str::uuid7(),
        'time_spent_ms' => 4200,
    ];
}

function openDay(int $day = 1): array
{
    return test()->postJson("/api/v1/student/days/{$day}/attempt")->json('data');
}

// ------------------------------------------------------------------ timeline ----

it('returns the month timeline', function (): void {
    $response = $this->getJson('/api/v1/student/days');

    $response->assertOk();

    expect($response->json())->toBeApiEnvelope()
        ->and($response->json('data.days'))->toHaveCount(3)
        ->and($response->json('data.days.0.status'))->toBe('available')
        ->and($response->json('data.days.1.status'))->toBe('locked')
        ->and($response->json('data.level.total_days'))->toBe(3)
        ->and($response->json('data.level_test.unlocked'))->toBeFalse();
});

it('locks a day the student has not reached with 423', function (): void {
    // 423 rather than 403, so the client can tell "not yet" from "not allowed".
    $response = $this->getJson('/api/v1/student/days/3');

    $response->assertStatus(423)
        ->assertJsonPath('errors.code', 'QUIZ_DAY_LOCKED');

    expect($response->json('meta.unlock_hint'))->not->toBeEmpty();
});

it('opens day 1 for a new student', function (): void {
    $response = $this->getJson('/api/v1/student/days/1');

    $response->assertOk()->assertJsonPath('data.unlocked', true);
});

// -------------------------------------------------------------------- attempt ----

it('starts a day and returns its questions without answers', function (): void {
    $response = $this->postJson('/api/v1/student/days/1/attempt');

    $response->assertOk()
        ->assertJsonPath('data.day_number', 1)
        ->assertJsonPath('data.required_count', 3)
        ->assertJsonPath('data.mastered_count', 0)
        ->assertJsonCount(3, 'data.questions');

    $body = $response->getContent();

    expect($body)->not->toContain('correct_option')
        ->not->toContain('explanation')
        ->not->toContain('is_correct');
});

it('resumes the same attempt when the day is opened again', function (): void {
    $first = openDay(1);
    $second = openDay(1);

    expect($second['attempt_uuid'])->toBe($first['attempt_uuid']);
});

it('refuses to start a locked day', function (): void {
    $this->postJson('/api/v1/student/days/3/attempt')
        ->assertStatus(423)
        ->assertJsonPath('errors.code', 'QUIZ_DAY_LOCKED');
});

// -------------------------------------------------------------------- answers ----

it('grades an answer on the server', function (): void {
    $attempt = openDay(1);
    $question = Question::query()->findOrFail($attempt['questions'][0]['question_id']);

    $response = $this->postJson(
        "/api/v1/student/attempts/{$attempt['attempt_uuid']}/answers",
        answerPayload($question->getKey(), $question->correct_option->value),
    );

    $response->assertOk()
        ->assertJsonPath('data.is_correct', true)
        ->assertJsonPath('data.progress.mastered_count', 1);
});

it('reveals the answer only after a wrong attempt', function (): void {
    $attempt = openDay(1);
    $question = Question::query()->findOrFail($attempt['questions'][0]['question_id']);
    $wrong = collect(['a', 'b', 'c', 'd'])
        ->first(fn (string $key): bool => $key !== $question->correct_option->value);

    $response = $this->postJson(
        "/api/v1/student/attempts/{$attempt['attempt_uuid']}/answers",
        answerPayload($question->getKey(), $wrong),
    );

    $response->assertOk()
        ->assertJsonPath('data.is_correct', false)
        ->assertJsonPath('data.correct_option', $question->correct_option->value)
        ->assertJsonPath('data.explanation', $question->explanation);
});

it('never caches an answer verdict', function (): void {
    // A cached verdict in the service worker would hand back the answer to a question
    // the student is about to be asked again.
    $attempt = openDay(1);
    $question = Question::query()->findOrFail($attempt['questions'][0]['question_id']);

    $response = $this->postJson(
        "/api/v1/student/attempts/{$attempt['attempt_uuid']}/answers",
        answerPayload($question->getKey(), $question->correct_option->value),
    );

    expect($response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('private');
});

it('rejects an answer with no client id', function (): void {
    $attempt = openDay(1);
    $question = Question::query()->findOrFail($attempt['questions'][0]['question_id']);

    $this->postJson("/api/v1/student/attempts/{$attempt['attempt_uuid']}/answers", [
        'question_id' => $question->getKey(),
        'selected_option' => $question->correct_option->value,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('client_answer_uuid');
});

it('ignores a client that claims its own answer was right', function (): void {
    $attempt = openDay(1);
    $question = Question::query()->findOrFail($attempt['questions'][0]['question_id']);
    $wrong = collect(['a', 'b', 'c', 'd'])
        ->first(fn (string $key): bool => $key !== $question->correct_option->value);

    $this->postJson("/api/v1/student/attempts/{$attempt['attempt_uuid']}/answers", [
        ...answerPayload($question->getKey(), $wrong),
        'is_correct' => true,
        'score' => 3,
    ])
        ->assertOk()
        ->assertJsonPath('data.is_correct', false)
        ->assertJsonPath('data.progress.mastered_count', 0);
});

it('counts a replayed answer once', function (): void {
    $attempt = openDay(1);
    $question = Question::query()->findOrFail($attempt['questions'][0]['question_id']);
    $payload = answerPayload($question->getKey(), $question->correct_option->value);

    $this->postJson("/api/v1/student/attempts/{$attempt['attempt_uuid']}/answers", $payload)->assertOk();

    $this->postJson("/api/v1/student/attempts/{$attempt['attempt_uuid']}/answers", $payload)
        ->assertOk()
        ->assertJsonPath('data.replayed', true)
        ->assertJsonPath('data.progress.mastered_count', 1);
});

// ------------------------------------------------------------------ completion ----

it('refuses to complete a day that is not finished', function (): void {
    $attempt = openDay(1);

    $this->postJson("/api/v1/student/attempts/{$attempt['attempt_uuid']}/complete")
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'QUIZ_NOT_COMPLETE')
        ->assertJsonCount(3, 'meta.outstanding_question_ids');
});

it('completes a day and unlocks the next', function (): void {
    $attempt = openDay(1);

    foreach ($attempt['questions'] as $item) {
        $question = Question::query()->findOrFail($item['question_id']);
        $this->postJson(
            "/api/v1/student/attempts/{$attempt['attempt_uuid']}/answers",
            answerPayload($question->getKey(), $question->correct_option->value),
        )->assertOk();
    }

    $this->postJson("/api/v1/student/attempts/{$attempt['attempt_uuid']}/complete")
        ->assertOk()
        ->assertJsonPath('data.score', 3)
        ->assertJsonPath('data.next_day.day', 2)
        ->assertJsonPath('data.next_day.unlocked', true)
        ->assertJsonPath('data.streak.current', 1);

    $this->getJson('/api/v1/student/days/2')->assertOk();
});

// --------------------------------------------------------------- other people ----

it('cannot see another student attempt', function (): void {
    // 404, not 403: a 403 would confirm the attempt exists and let ids be probed.
    $mine = openDay(1);

    $other = QuizScenario::make(days: 3, perDay: 3);
    app(AssignLevelQuestionsAction::class)($other->enrolment);
    actingAsStudent($other->student);

    $this->getJson("/api/v1/student/attempts/{$mine['attempt_uuid']}")->assertNotFound();
});

it('cannot answer into another student attempt', function (): void {
    $mine = openDay(1);
    $questionId = $mine['questions'][0]['question_id'];

    $other = QuizScenario::make(days: 3, perDay: 3);
    app(AssignLevelQuestionsAction::class)($other->enrolment);
    actingAsStudent($other->student);

    $this->postJson(
        "/api/v1/student/attempts/{$mine['attempt_uuid']}/answers",
        answerPayload($questionId, 'a'),
    )->assertNotFound();
});

it('turns anyone who is not signed in away', function (): void {
    app()['auth']->forgetGuards();

    $this->getJson('/api/v1/student/days')->assertUnauthorized();
});

it('keeps admins out of student endpoints', function (): void {
    app()['auth']->forgetGuards();
    actingAsAdmin();

    $this->getJson('/api/v1/student/days')
        ->assertForbidden()
        ->assertJsonPath('errors.code', 'FORBIDDEN_ROLE');
});
