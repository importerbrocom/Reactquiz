<?php

declare(strict_types=1);

use App\Http\Resources\QuestionAdminResource;
use App\Http\Resources\QuestionForStudentResource;
use App\Models\Level;
use App\Models\Question;
use Illuminate\Support\Facades\Route;

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

it('excludes every answer field from the student resource', function (): void {
    $payload = (new QuestionForStudentResource($this->question))->toArray(request());

    expect($payload)->toHaveKeys(['id', 'question_text', 'options'])
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
    $payload = (new QuestionForStudentResource($this->question))->toArray(request());

    foreach ($payload['options'] as $option) {
        expect($option)->toHaveKeys(['key', 'text'])
            ->and($option)->not->toHaveKey('is_correct');
    }
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

    $payload = (new QuestionForStudentResource($this->question, $order))->toArray(request());

    expect(array_column($payload['options'], 'key'))->toBe($order);

    // The key still identifies the canonical option, so the client submits a key and
    // the server compares it to questions.correct_option with no translation step.
    $byKey = collect($payload['options'])->keyBy('key');
    foreach ($this->question->options as $option) {
        expect($byKey[$option->option_key->value]['text'])->toBe($option->option_text);
    }
});

it('leaks no answer content from any non-admin GET endpoint', function (): void {
    actingAsStudent();

    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1'))
        ->reject(fn ($route) => str_contains($route->uri(), 'admin'))
        ->reject(fn ($route) => str_contains($route->uri(), '{'))   // needs binding
        ->map(fn ($route) => $route->uri());

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $uri) {
        $body = $this->getJson('/'.$uri)->getContent();

        expect($body)
            ->not->toContain('SENTINEL_EXPLANATION_STRING')
            ->not->toContain('SENTINEL_ANSWER_TEXT')
            ->not->toContain('correct_option');
    }
});
