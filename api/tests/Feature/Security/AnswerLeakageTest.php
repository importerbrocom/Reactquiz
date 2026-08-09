<?php

declare(strict_types=1);

use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\Actions\Quiz\StartQuizAttemptAction;
use App\Http\Resources\QuestionAdminResource;
use App\Models\Level;
use App\Models\Question;
use App\Services\Quiz\QuestionDeliveryService;
use Illuminate\Support\Facades\Route;
use Tests\Support\QuizScenario;

/**
 * The most important test in the suite.
 *
 * Questions are the product's core asset. If a correct answer can be read before
 * the student submits, the whole learning model collapses and the content cannot be
 * un-leaked. These assertions are deliberately paranoid and cheap to run.
 */
beforeEach(function (): void {
    $this->level = Level::factory()->create();
    $this->question = Question::factory()->forLevel($this->level)->create([
        'question_text' => 'Which chamber pumps oxygenated blood into the aorta?',
        'explanation' => 'SENTINEL_EXPLANATION_STRING',
        'correct_answer_text' => 'SENTINEL_ANSWER_TEXT',
    ]);
    $this->question->load('options');
});

/** Load and present the question exactly as a student endpoint would. */
function present(?array $order = null): array
{
    $service = app(QuestionDeliveryService::class);
    $question = $service->load([test()->question->getKey()])->first();

    if ($order !== null) {
        // Force a specific display order to check that keys stay canonical.
        $question->forceFill(['shuffle_options' => false])->syncOriginal();
        $question->options->each(fn ($option) => $option->forceFill([
            'display_order' => array_search($option->option_key->value, $order, true),
        ]));
    }

    return $service->present($question, position: 1, scope: 'leak-test');
}

it('excludes every answer field from the student payload', function (): void {
    $payload = present();

    expect($payload)->toHaveKeys(['question_id', 'question_text', 'options'])
        ->and($payload)->not->toHaveKey('correct_option')
        ->and($payload)->not->toHaveKey('correct_answer_text')
        ->and($payload)->not->toHaveKey('explanation');

    $encoded = json_encode($payload);

    expect($encoded)
        ->not->toContain('SENTINEL_EXPLANATION_STRING')
        ->not->toContain('SENTINEL_ANSWER_TEXT')
        ->not->toContain('correct_option')
        ->not->toContain('is_correct');
});

it('does not mark which option is correct', function (): void {
    $payload = present();

    foreach ($payload['options'] as $option) {
        expect($option)->toHaveKeys(['key', 'text'])
            ->and($option)->not->toHaveKey('is_correct');
    }
});

it('does not even load the answer columns onto the model', function (): void {
    // The strongest form of the guarantee: the values are not in memory, so no future
    // ->toArray(), ->append() or debug dump can reveal them.
    $question = app(QuestionDeliveryService::class)->load([$this->question->getKey()])->first();

    expect($question->getAttributes())->not->toHaveKey('correct_option')
        ->and($question->getAttributes())->not->toHaveKey('correct_answer_text')
        ->and($question->getAttributes())->not->toHaveKey('explanation')
        ->and($question->options->first()->getAttributes())->not->toHaveKey('is_correct');
});

it('hides answer fields even from a raw model serialisation', function (): void {
    // Defence in depth: a careless response()->json($question) must not leak either.
    $array = $this->question->toArray();

    expect($array)->not->toHaveKey('correct_option')
        ->and($array)->not->toHaveKey('correct_answer_text')
        ->and($array)->not->toHaveKey('explanation')
        ->and(json_encode($array))->not->toContain('SENTINEL_EXPLANATION_STRING');
});

it('hides the correct flag on a raw option serialisation', function (): void {
    expect($this->question->options->first()->toArray())->not->toHaveKey('is_correct');
});

it('still exposes answers to the admin resource', function (): void {
    // The counter-test: if this fails, admins cannot edit questions.
    $payload = (new QuestionAdminResource($this->question))->toArray(request());

    expect($payload['explanation'])->toBe('SENTINEL_EXPLANATION_STRING')
        ->and($payload['correct_answer_text'])->toBe('SENTINEL_ANSWER_TEXT')
        ->and($payload['correct_option'])->toBe($this->question->correct_option->value)
        ->and(collect($payload['options'])->firstWhere('is_correct', true))->not->toBeNull();
});

it('honours a per-exposure option order while keeping canonical keys', function (): void {
    $order = ['c', 'a', 'd', 'b'];

    $payload = present($order);

    expect(array_column($payload['options'], 'key'))->toBe($order);

    // The key still identifies the canonical option, so the client submits a key and
    // the server compares it to questions.correct_option with no translation step.
    $byKey = collect($payload['options'])->keyBy('key');
    foreach ($this->question->options as $option) {
        expect($byKey[$option->option_key->value]['text'])->toBe($option->option_text);
    }
});

it('leaks no answer content from any non-admin GET endpoint', function (): void {
    // A REAL enrolled student partway through a day, so the sweep actually renders
    // question payloads. With an un-enrolled student every endpoint would 403 and this
    // test would pass without inspecting anything.
    $scenario = QuizScenario::make(days: 2, perDay: 2);
    $scenario->level()->questions()->update([
        'explanation' => 'SENTINEL_EXPLANATION_STRING',
        'correct_answer_text' => 'SENTINEL_ANSWER_TEXT',
    ]);
    app(AssignLevelQuestionsAction::class)($scenario->enrolment);
    app(StartQuizAttemptAction::class)($scenario->enrolment, 1);
    actingAsStudent($scenario->student);

    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1'))
        ->reject(fn ($route) => str_contains($route->uri(), 'admin'))
        ->reject(fn ($route) => str_contains($route->uri(), '{'))   // needs binding
        ->map(fn ($route) => $route->uri());

    expect($routes)->not->toBeEmpty();

    $answered = 0;

    foreach ($routes as $uri) {
        $response = $this->getJson('/'.$uri);
        $body = $response->getContent();

        if ($response->getStatusCode() === 200) {
            $answered++;
        }

        expect($body)
            ->not->toContain('SENTINEL_EXPLANATION_STRING')
            ->not->toContain('SENTINEL_ANSWER_TEXT')
            ->not->toContain('correct_option');
    }

    // Guards the guard: if a refactor made every one of these 403 or 404, the loop above
    // would still pass while inspecting nothing.
    expect($answered)->toBeGreaterThanOrEqual(5, 'the sweep did not reach any live endpoint');
});

it('leaks no answer content from a day payload delivered over HTTP', function (): void {
    // The route sweep skips URIs with parameters, and the day payload is the single
    // biggest question payload in the product. Covered explicitly.
    $scenario = QuizScenario::make(days: 2, perDay: 3);
    $scenario->level()->questions()->update([
        'explanation' => 'SENTINEL_EXPLANATION_STRING',
        'correct_answer_text' => 'SENTINEL_ANSWER_TEXT',
    ]);
    app(AssignLevelQuestionsAction::class)($scenario->enrolment);
    actingAsStudent($scenario->student);

    $body = $this->postJson('/api/v1/student/days/1/attempt')->assertOk()->getContent();

    expect($body)
        ->not->toContain('SENTINEL_EXPLANATION_STRING')
        ->not->toContain('SENTINEL_ANSWER_TEXT')
        ->not->toContain('correct_option')
        ->not->toContain('is_correct');
});

it('leaks no answer content from a month-end test window over HTTP', function (): void {
    $scenario = QuizScenario::make(days: 2, perDay: 3);
    $scenario->level()->questions()->update([
        'explanation' => 'SENTINEL_EXPLANATION_STRING',
        'correct_answer_text' => 'SENTINEL_ANSWER_TEXT',
    ]);
    app(AssignLevelQuestionsAction::class)($scenario->enrolment);
    $scenario->completeAllDays();
    actingAsStudent($scenario->student);

    $body = $this->postJson('/api/v1/student/level-test/attempt')->assertOk()->getContent();

    expect($body)
        ->not->toContain('SENTINEL_EXPLANATION_STRING')
        ->not->toContain('SENTINEL_ANSWER_TEXT')
        ->not->toContain('correct_option')
        ->not->toContain('is_correct');
});
