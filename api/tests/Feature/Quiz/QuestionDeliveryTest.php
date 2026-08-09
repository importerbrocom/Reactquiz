<?php

declare(strict_types=1);

use App\Actions\LevelTest\StartLevelTestAction;
use App\Actions\LevelTest\SubmitLevelTestAction;
use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\Actions\Quiz\StartQuizAttemptAction;
use App\Actions\Quiz\SubmitAnswerAction;
use App\DTOs\Quiz\AnswerSubmissionData;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\Quiz\DailyQuizDeliveryService;
use App\Services\Quiz\LevelTestDeliveryService;
use App\Services\Quiz\QuestionDeliveryService;
use Illuminate\Support\Str;
use Tests\Support\QuizScenario;

/**
 * What the student's device is allowed to know.
 *
 * The PWA caches quiz payloads so it works offline, which means anything sent here is
 * effectively handed to the student — inspectable in devtools, readable in IndexedDB.
 * So these tests are less about shape than about a single guarantee: the payload does
 * not contain the answer, in any field, at any nesting depth.
 */
beforeEach(function (): void {
    $this->daily = app(DailyQuizDeliveryService::class);
    $this->testDelivery = app(LevelTestDeliveryService::class);
    $this->startDay = app(StartQuizAttemptAction::class);

    $this->scenario = QuizScenario::make(days: 3, perDay: 4);
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
});

/** Every scalar in a nested payload, flattened, for leakage scanning. */
function scalars(array $payload): array
{
    $out = [];
    array_walk_recursive($payload, function (mixed $value) use (&$out): void {
        if (is_scalar($value)) {
            $out[] = (string) $value;
        }
    });

    return $out;
}

function keysDeep(array $payload): array
{
    $keys = [];
    $walk = function (array $node) use (&$walk, &$keys): void {
        foreach ($node as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }
            if (is_array($value)) {
                $walk($value);
            }
        }
    };
    $walk($payload);

    return array_unique($keys);
}

// ------------------------------------------------------------- no leakage ----

it('sends no explanation with a daily quiz', function (): void {
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);
    $payload = $this->daily->payload($attempt);

    $questions = Question::query()
        ->whereIn('id', collect($payload['questions'])->pluck('question_id'))
        ->get();

    $values = scalars($payload);

    foreach ($questions as $question) {
        expect($values)->not->toContain($question->explanation);
    }

    expect(keysDeep($payload))
        ->not->toContain('correct_option')
        ->not->toContain('correct_answer_text')
        ->not->toContain('explanation')
        ->not->toContain('is_correct');
});

it('gives the correct option no distinguishing mark', function (): void {
    // The correct answer's TEXT is necessarily in the payload — it is one of the four
    // choices. What must not be there is anything that singles it out. So: every option
    // carries exactly the same set of fields, and the right one is not identifiable by
    // its shape, its ordering, or an extra key.
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);
    $payload = $this->daily->payload($attempt);

    foreach ($payload['questions'] as $question) {
        $shapes = collect($question['options'])
            ->map(fn (array $option): array => array_keys($option))
            ->unique(fn (array $keys): string => implode(',', $keys));

        expect($shapes)->toHaveCount(1)
            ->and($shapes->first())->toBe(['key', 'display_position', 'text', 'image']);
    }
});

it('does not park the correct answer in the same slot across a day', function (): void {
    // If the shuffle were seeded per-question only, the answer could land in the same
    // display position for every question in the day — a tell worth more than the text.
    $scenario = QuizScenario::make(days: 1, perDay: 40);
    app(AssignLevelQuestionsAction::class)($scenario->enrolment);
    $attempt = ($this->startDay)($scenario->enrolment, 1);

    $correct = Question::query()
        ->whereIn('id', $attempt->questions()->pluck('question_id'))
        ->pluck('correct_option', 'id');

    $slots = collect($this->daily->payload($attempt)['questions'])
        ->map(fn (array $q): int => collect($q['options'])
            ->search(fn (array $o): bool => $o['key'] === $correct[$q['question_id']]->value) + 1)
        ->countBy();

    expect($slots->keys())->toHaveCount(4)
        ->and($slots->max())->toBeLessThan(20);
});

it('sends no explanation with a test window', function (): void {
    $this->scenario->completeAllDays();
    $attempt = app(StartLevelTestAction::class)($this->scenario->enrolment->refresh());

    $payload = $this->testDelivery->window($attempt, 1, 12);
    $values = scalars($payload);

    foreach (Question::query()->whereIn('id', $attempt->question_order)->get() as $question) {
        expect($values)->not->toContain($question->explanation);
    }

    expect(keysDeep($payload))
        ->not->toContain('correct_option')
        ->not->toContain('explanation')
        ->not->toContain('is_correct');
});

it('never even loads the answer columns', function (): void {
    // Belt and braces: the guarantee holds because the columns are not SELECTed, so a
    // future `toArray()` or a debug dump of the model cannot leak them either.
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);
    $payload = $this->daily->payload($attempt);

    $loaded = app(QuestionDeliveryService::class)
        ->load([$payload['questions'][0]['question_id']])
        ->first();

    expect($loaded->getAttributes())->not->toHaveKey('correct_option')
        ->and($loaded->getAttributes())->not->toHaveKey('explanation')
        ->and($loaded->options->first()->getAttributes())->not->toHaveKey('is_correct');
});

// --------------------------------------------------------------- daily shape ----

it('delivers the day in assignment order with stable option keys', function (): void {
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);
    $payload = $this->daily->payload($attempt);

    expect($payload['questions'])->toHaveCount(4)
        ->and($payload['day_number'])->toBe(1)
        ->and($payload['required_count'])->toBe(4)
        ->and($payload['mastered_count'])->toBe(0)
        ->and(collect($payload['questions'])->pluck('position')->all())->toBe([1, 2, 3, 4]);

    $options = $payload['questions'][0]['options'];

    expect($options)->toHaveCount(4)
        // Identity and display position are separate fields, deliberately.
        ->and(collect($options)->pluck('display_position')->all())->toBe([1, 2, 3, 4])
        ->and(collect($options)->pluck('key')->sort()->values()->all())->toBe(['a', 'b', 'c', 'd']);
});

it('points a returning student at their first unfinished question', function (): void {
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);
    $questionIds = collect($this->daily->payload($attempt)['questions'])->pluck('question_id')->all();

    $first = Question::query()->findOrFail($questionIds[0]);
    app(SubmitAnswerAction::class)($attempt, new AnswerSubmissionData(
        questionId: $first->getKey(),
        selectedOption: $first->correct_option,
        clientAnswerUuid: (string) Str::uuid7(),
        timeSpentMs: 1000,
        answeredAt: null,
    ));

    expect($this->daily->payload($attempt->refresh())['resume_question_id'])->toBe($questionIds[1]);
});

it('echoes the student choice back by key, not by position', function (): void {
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);
    $question = Question::query()->findOrFail(
        $attempt->questions()->orderBy('position')->value('question_id'),
    );

    app(SubmitAnswerAction::class)($attempt, new AnswerSubmissionData(
        questionId: $question->getKey(),
        selectedOption: $question->correct_option,
        clientAnswerUuid: (string) Str::uuid7(),
        timeSpentMs: 1000,
        answeredAt: null,
    ));

    $delivered = collect($this->daily->payload($attempt->refresh())['questions'])
        ->firstWhere('question_id', $question->getKey());

    // The order moved after the submission, so a stored position would now be wrong.
    expect($delivered['selected_option'])->toBe($question->correct_option->value)
        ->and($delivered['state'])->toBe('mastered');
});

it('holds the option order steady until the student answers', function (): void {
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);

    $before = collect($this->daily->payload($attempt)['questions'])
        ->mapWithKeys(fn (array $q): array => [$q['question_id'] => collect($q['options'])->pluck('key')->all()]);

    // A refresh, and a reload from the service-worker cache.
    foreach (range(1, 3) as $ignored) {
        $again = collect($this->daily->payload($attempt->refresh())['questions'])
            ->mapWithKeys(fn (array $q): array => [$q['question_id'] => collect($q['options'])->pluck('key')->all()]);

        expect($again->all())->toBe($before->all());
    }
});

it('moves the option order after the student answers', function (): void {
    $scenario = QuizScenario::make(days: 3, perDay: 30);
    app(AssignLevelQuestionsAction::class)($scenario->enrolment);
    $attempt = ($this->startDay)($scenario->enrolment, 1);

    $before = collect($this->daily->payload($attempt)['questions'])
        ->mapWithKeys(fn (array $q): array => [$q['question_id'] => collect($q['options'])->pluck('key')->all()]);

    foreach ($attempt->questions()->pluck('question_id') as $questionId) {
        $question = Question::query()->findOrFail($questionId);
        app(SubmitAnswerAction::class)($attempt->refresh(), new AnswerSubmissionData(
            questionId: $questionId,
            selectedOption: $question->correct_option,
            clientAnswerUuid: (string) Str::uuid7(),
            timeSpentMs: 1000,
            answeredAt: null,
        ));
    }

    $after = collect($this->daily->payload($attempt->refresh())['questions'])
        ->mapWithKeys(fn (array $q): array => [$q['question_id'] => collect($q['options'])->pluck('key')->all()]);

    $moved = $before->filter(fn (array $keys, int $id): bool => $keys !== $after->get($id));

    // 1 in 24 permutations repeats by chance, so not all 30 must move.
    expect($moved->count())->toBeGreaterThan(22);
});

it('delivers a day in a constant number of queries', function (): void {
    // Ten questions must not mean ten option queries.
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);
    $this->daily->payload($attempt);   // warm any lazy relation on the attempt itself

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->daily->payload($attempt->refresh());
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBeLessThanOrEqual(8, "delivery used {$count} queries");
});

// ---------------------------------------------------------------- windowing ----

it('serves the test in windows rather than all at once', function (): void {
    $scenario = QuizScenario::realistic();
    app(AssignLevelQuestionsAction::class)($scenario->enrolment);
    $scenario->completeAllDays();
    $attempt = app(StartLevelTestAction::class)($scenario->enrolment->refresh());

    $first = $this->testDelivery->window($attempt, 1);

    expect($first['questions'])->toHaveCount((int) config('quiz.test.window_size'))
        ->and($first['total_questions'])->toBe(300)
        ->and($first['window']['from'])->toBe(1)
        ->and($first['window']['has_more'])->toBeTrue()
        ->and($first['window']['next_position'])->toBe(21);
});

it('refuses to serve a bigger window than configured', function (): void {
    // A client asking for limit=3000 does not get the whole paper.
    $scenario = QuizScenario::realistic();
    app(AssignLevelQuestionsAction::class)($scenario->enrolment);
    $scenario->completeAllDays();
    $attempt = app(StartLevelTestAction::class)($scenario->enrolment->refresh());

    $window = $this->testDelivery->window($attempt, 1, 3000);

    expect($window['questions'])->toHaveCount((int) config('quiz.test.window_size'));
});

it('walks the window in the persisted paper order', function (): void {
    $this->scenario->completeAllDays();
    $attempt = app(StartLevelTestAction::class)($this->scenario->enrolment->refresh());

    $window = $this->testDelivery->window($attempt, 4, 5);

    expect(collect($window['questions'])->pluck('question_id')->all())
        ->toBe(array_slice($attempt->question_order, 3, 5))
        ->and(collect($window['questions'])->pluck('position')->all())->toBe([4, 5, 6, 7, 8]);
});

it('closes the last window', function (): void {
    $this->scenario->completeAllDays();
    $attempt = app(StartLevelTestAction::class)($this->scenario->enrolment->refresh());

    $window = $this->testDelivery->window($attempt, 11, 20);

    expect($window['questions'])->toHaveCount(2)
        ->and($window['window']['has_more'])->toBeFalse()
        ->and($window['window']['next_position'])->toBeNull();
});

it('keeps the option order fixed while the student walks the paper', function (): void {
    // Flicking back to question 3 must not reshuffle it — that reads as a bug and
    // invites a misclick. The per-exposure guarantee is met by the retake instead.
    $this->scenario->completeAllDays();
    $attempt = app(StartLevelTestAction::class)($this->scenario->enrolment->refresh());

    $first = $this->testDelivery->window($attempt, 1, 4);
    $revisit = $this->testDelivery->window($attempt->refresh(), 1, 4);

    expect(collect($revisit['questions'])->pluck('options.0.key')->all())
        ->toBe(collect($first['questions'])->pluck('options.0.key')->all());
});

it('reshuffles the options on a retake', function (): void {
    $scenario = QuizScenario::realistic();
    app(AssignLevelQuestionsAction::class)($scenario->enrolment);
    $scenario->completeAllDays();

    $first = app(StartLevelTestAction::class)($scenario->enrolment->refresh());
    $firstOrders = collect($this->testDelivery->window($first, 1, 20)['questions'])
        ->mapWithKeys(fn (array $q): array => [$q['question_id'] => collect($q['options'])->pluck('key')->all()]);

    app(SubmitLevelTestAction::class)($first);
    $second = app(StartLevelTestAction::class)($scenario->enrolment->refresh());
    $secondOrders = collect($this->testDelivery->window($second, 1, 20)['questions'])
        ->mapWithKeys(fn (array $q): array => [$q['question_id'] => collect($q['options'])->pluck('key')->all()]);

    // Different questions in this window AND different option orders for any overlap.
    $overlap = $firstOrders->keys()->intersect($secondOrders->keys());
    $sameOrder = $overlap->filter(fn (int $id): bool => $firstOrders[$id] === $secondOrders[$id]);

    expect($sameOrder->count())->toBeLessThan(max(1, (int) ceil($overlap->count() / 2)));
});

it('serves a window in a constant number of queries', function (): void {
    $scenario = QuizScenario::realistic();
    app(AssignLevelQuestionsAction::class)($scenario->enrolment);
    $scenario->completeAllDays();
    $attempt = app(StartLevelTestAction::class)($scenario->enrolment->refresh());

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->testDelivery->window($attempt, 1, 20);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Questions + their options + saved answers. Not 20 x anything.
    expect($count)->toBeLessThanOrEqual(4, "window used {$count} queries");
});

it('builds a navigator that reveals nothing about correctness', function (): void {
    $this->scenario->completeAllDays();
    $attempt = app(StartLevelTestAction::class)($this->scenario->enrolment->refresh());

    $map = $this->testDelivery->navigator($attempt);

    expect($map)->toHaveCount(12)
        ->and($map[0])->toHaveKeys(['position', 'question_id', 'answered', 'flagged'])
        ->and(array_keys($map[0]))->not->toContain('is_correct')
        ->and(collect($map)->pluck('answered')->unique()->all())->toBe([false]);
});

// ----------------------------------------------------------- pinned options ----

it('keeps a pinned option last in a delivered payload', function (): void {
    $attempt = ($this->startDay)($this->scenario->enrolment, 1);
    QuestionOption::query()
        ->whereIn('question_id', $attempt->questions()->pluck('question_id'))
        ->where('option_key', 'd')
        ->update(['pin_last' => true, 'option_text' => 'All of the above']);

    foreach ($this->daily->payload($attempt->refresh())['questions'] as $question) {
        expect(collect($question['options'])->last()['text'])->toBe('All of the above');
    }
});
