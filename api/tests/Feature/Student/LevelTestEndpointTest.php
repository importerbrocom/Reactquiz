<?php

declare(strict_types=1);

use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\Models\Question;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\QuizScenario;

/**
 * The month-end test over HTTP, including the windowing the PWA depends on.
 */
beforeEach(function (): void {
    $this->scenario = QuizScenario::make(days: 4, perDay: 3);
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
    actingAsStudent($this->scenario->student);
});

function startTest(): array
{
    return test()->postJson('/api/v1/student/level-test/attempt')->json('data');
}

/** @param array<int, array{0: int, 1: ?string}> $pairs */
function syncBatch(string $uuid, array $pairs): TestResponse
{
    return test()->postJson("/api/v1/student/level-test/attempts/{$uuid}/answers", [
        'client_batch_uuid' => (string) Str::uuid7(),
        'answers' => array_map(static fn (array $pair): array => [
            'question_id' => $pair[0],
            'selected_option' => $pair[1],
        ], $pairs),
    ]);
}

// ------------------------------------------------------------- availability ----

it('reports the test as locked until the month is done', function (): void {
    $response = $this->getJson('/api/v1/student/level-test');

    $response->assertOk()
        ->assertJsonPath('data.eligibility.unlocked', false)
        ->assertJsonPath('data.eligibility.reason', 'level_test_not_eligible')
        ->assertJsonPath('data.open_attempt', null);
});

it('refuses to start the test early', function (): void {
    $this->postJson('/api/v1/student/level-test/attempt')
        ->assertStatus(423)
        ->assertJsonPath('errors.code', 'LEVEL_TEST_NOT_ELIGIBLE');
});

it('opens the test once every day is complete', function (): void {
    $this->scenario->completeAllDays();

    $this->getJson('/api/v1/student/level-test')
        ->assertOk()
        ->assertJsonPath('data.eligibility.unlocked', true)
        ->assertJsonPath('data.test.question_count', 12)
        ->assertJsonPath('data.test.unlimited_attempts', true);
});

// ---------------------------------------------------------------- windowing ----

it('serves the first window on start', function (): void {
    $this->scenario->completeAllDays();

    $response = $this->postJson('/api/v1/student/level-test/attempt');

    $response->assertOk()
        ->assertJsonPath('data.total_questions', 12)
        ->assertJsonPath('data.window.from', 1)
        ->assertJsonCount(12, 'data.questions');

    expect($response->getContent())
        ->not->toContain('correct_option')
        ->not->toContain('explanation')
        ->not->toContain('is_correct');
});

it('pages through the paper', function (): void {
    $this->scenario->completeAllDays();
    $attempt = startTest();

    $second = $this->getJson(
        "/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/questions?position=6&limit=4",
    );

    $second->assertOk()
        ->assertJsonPath('data.window.from', 6)
        ->assertJsonCount(4, 'data.questions')
        ->assertJsonPath('data.questions.0.position', 6);
});

it('refuses a window larger than the configured size', function (): void {
    $this->scenario->completeAllDays();
    $attempt = startTest();

    $this->getJson(
        "/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/questions?limit=5000",
    )
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});

it('serves a navigator with no hint of correctness', function (): void {
    $this->scenario->completeAllDays();
    $attempt = startTest();

    $response = $this->getJson("/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/navigator");

    $response->assertOk()->assertJsonCount(12, 'data.questions');

    expect($response->getContent())->not->toContain('is_correct');
});

// ------------------------------------------------------------------- syncing ----

it('saves a batch of answers and reports progress', function (): void {
    $this->scenario->completeAllDays();
    $attempt = startTest();
    $ids = collect($attempt['questions'])->pluck('question_id')->all();

    $response = syncBatch($attempt['attempt_uuid'], [
        [$ids[0], 'a'],
        [$ids[1], 'b'],
        [$ids[2], null],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.accepted', 3)
        ->assertJsonPath('data.progress.answered_count', 2);

    // Not a word about whether any of it was right.
    expect($response->getContent())->not->toContain('is_correct');
});

it('rejects an oversized batch before it reaches the database', function (): void {
    $this->scenario->completeAllDays();
    $attempt = startTest();
    $ids = collect($attempt['questions'])->pluck('question_id')->all();

    $answers = [];
    foreach (range(1, 40) as $i) {
        $answers[] = ['question_id' => $ids[0] + $i, 'selected_option' => 'a'];
    }

    $this->postJson("/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/answers", [
        'client_batch_uuid' => (string) Str::uuid7(),
        'answers' => $answers,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answers');
});

it('reports questions that are not on this paper', function (): void {
    $this->scenario->completeAllDays();
    $attempt = startTest();
    $foreign = Question::factory()->forLevel($this->scenario->level())->create();

    syncBatch($attempt['attempt_uuid'], [[$foreign->getKey(), 'a']])
        ->assertOk()
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.rejected', 1);
});

// -------------------------------------------------------------- submit/result ----

it('grades the paper on submit', function (): void {
    $this->scenario->completeAllDays();
    $attempt = startTest();

    $questions = Question::query()
        ->whereIn('id', collect($attempt['questions'])->pluck('question_id'))
        ->get();

    $pairs = $questions->take(9)
        ->map(fn (Question $q): array => [$q->getKey(), $q->correct_option->value])
        ->all();

    syncBatch($attempt['attempt_uuid'], $pairs)->assertOk();

    $this->postJson("/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/submit")
        ->assertOk()
        ->assertJsonPath('data.graded', true)
        ->assertJsonPath('data.score.correct', 9)
        ->assertJsonPath('data.score.unanswered', 3)
        ->assertJsonPath('data.score.passed', true);
});

it('is safe to submit twice', function (): void {
    $this->scenario->completeAllDays();
    $attempt = startTest();

    $first = $this->postJson("/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/submit");
    $second = $this->postJson("/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/submit");

    $first->assertOk();
    $second->assertOk()
        ->assertJsonPath('data.submitted_at', $first->json('data.submitted_at'));
});

it('refuses to save answers after submission', function (): void {
    $this->scenario->completeAllDays();
    $attempt = startTest();
    $ids = collect($attempt['questions'])->pluck('question_id')->all();

    $this->postJson("/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/submit")->assertOk();

    syncBatch($attempt['attempt_uuid'], [[$ids[0], 'a']])
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'TEST_ATTEMPT_CLOSED');
});

it('withholds the score until results are released', function (): void {
    $this->scenario->level()->forceFill(['release_results_immediately' => false])->save();
    $this->scenario->completeAllDays();
    $attempt = startTest();

    $response = $this->postJson("/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/submit");

    $response->assertOk()
        ->assertJsonPath('data.graded', true)
        ->assertJsonPath('data.results_released', false);

    // The whole score block is absent, not merely zeroed.
    expect($response->json('data'))->not->toHaveKey('score');
});

it('never caches a result', function (): void {
    $this->scenario->completeAllDays();
    $attempt = startTest();
    $this->postJson("/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/submit")->assertOk();

    $response = $this->getJson("/api/v1/student/level-test/attempts/{$attempt['attempt_uuid']}/result");

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('allows an unlimited retake', function (): void {
    $this->scenario->completeAllDays();
    $first = startTest();
    $this->postJson("/api/v1/student/level-test/attempts/{$first['attempt_uuid']}/submit")->assertOk();

    $second = startTest();

    expect($second['attempt_uuid'])->not->toBe($first['attempt_uuid']);

    $this->getJson('/api/v1/student/level-test')
        ->assertOk()
        ->assertJsonCount(2, 'data.attempts');
});

// --------------------------------------------------------------- other people ----

it('cannot open another student test attempt', function (): void {
    $this->scenario->completeAllDays();
    $mine = startTest();

    $other = QuizScenario::make(days: 4, perDay: 3);
    app(AssignLevelQuestionsAction::class)($other->enrolment);
    app()['auth']->forgetGuards();
    actingAsStudent($other->student);

    $this->getJson("/api/v1/student/level-test/attempts/{$mine['attempt_uuid']}/questions")
        ->assertNotFound();
    $this->postJson("/api/v1/student/level-test/attempts/{$mine['attempt_uuid']}/submit")
        ->assertNotFound();
});
