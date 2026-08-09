<?php

declare(strict_types=1);

use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\Enums\ContentStatus;
use App\Enums\SelectionMode;
use App\Exceptions\InsufficientQuestionPoolException;
use App\Models\LevelEnrollment;
use App\Models\Question;
use App\Models\User;
use Tests\Support\QuizScenario;

/**
 * docs/adr/001 — per-student question assignment.
 *
 * The requirement that drives all of this: 100 students starting on the same day must
 * not receive the same ten questions, yet every student must still complete all 300.
 */
beforeEach(function (): void {
    $this->assign = app(AssignLevelQuestionsAction::class);
});

it('deals every question in the pool across the days', function (): void {
    $scenario = QuizScenario::make(days: 5, perDay: 4);      // pool of 20

    $count = ($this->assign)($scenario->enrolment);

    expect($count)->toBe(20)
        ->and($scenario->enrolment->dayQuestions()->count())->toBe(20);

    // Each day holds exactly daily_question_count questions.
    foreach (range(1, 5) as $day) {
        expect($scenario->enrolment->dayQuestions()->where('day_number', $day)->count())->toBe(4);
    }
});

it('gives a student every question exactly once', function (): void {
    $scenario = QuizScenario::make(days: 5, perDay: 4);
    ($this->assign)($scenario->enrolment);

    $assigned = $scenario->enrolment->dayQuestions()->pluck('question_id');
    $pool = $scenario->level()->questions()->pluck('id');

    expect($assigned)->toHaveCount(20)
        ->and($assigned->unique())->toHaveCount(20)
        ->and($assigned->sort()->values()->all())->toBe($pool->sort()->values()->all());
});

it('gives two students completely different first days', function (): void {
    $scenario = QuizScenario::make(days: 5, perDay: 4);
    ($this->assign)($scenario->enrolment);

    $studentB = LevelEnrollment::factory()->create([
        'programme_enrollment_id' => $scenario->programmeEnrollment->getKey(),
        'user_id' => User::factory()->create()->getKey(),
        'level_id' => $scenario->level()->getKey(),
        'cycle_number' => 1,
    ]);
    ($this->assign)($studentB);

    $dayOneA = $scenario->enrolment->dayQuestions()
        ->where('day_number', 1)->orderBy('position')->pluck('question_id')->all();
    $dayOneB = $studentB->dayQuestions()
        ->where('day_number', 1)->orderBy('position')->pluck('question_id')->all();

    expect($dayOneA)->not->toBe($dayOneB);
});

it('is reproducible from the stored seed', function (): void {
    $scenario = QuizScenario::make(days: 4, perDay: 5);
    ($this->assign)($scenario->enrolment);
    $first = $scenario->enrolment->dayQuestions()
        ->orderBy('day_number')->orderBy('position')->pluck('question_id')->all();

    // A second enrolment with the SAME seed must be dealt identically — this is what
    // makes support able to reconstruct what a student saw months later.
    $replica = LevelEnrollment::factory()->create([
        'programme_enrollment_id' => $scenario->programmeEnrollment->getKey(),
        'user_id' => User::factory()->create()->getKey(),
        'level_id' => $scenario->level()->getKey(),
        'cycle_number' => 1,
        'assignment_seed' => $scenario->enrolment->assignment_seed,
    ]);
    ($this->assign)($replica);

    expect($replica->dayQuestions()->orderBy('day_number')->orderBy('position')
        ->pluck('question_id')->all())->toBe($first);
});

it('re-deals the same questions differently for a repeat cycle', function (): void {
    $scenario = QuizScenario::make(days: 5, perDay: 4);
    ($this->assign)($scenario->enrolment);
    $cycleOne = $scenario->enrolment->dayQuestions()
        ->where('day_number', 1)->orderBy('position')->pluck('question_id')->all();

    $cycleTwo = $scenario->enrolInLevel(1, cycle: 2);
    ($this->assign)($cycleTwo);
    $cycleTwoDayOne = $cycleTwo->dayQuestions()
        ->where('day_number', 1)->orderBy('position')->pluck('question_id')->all();

    // Same 20 questions overall...
    expect($cycleTwo->dayQuestions()->pluck('question_id')->sort()->values())
        ->toEqual($scenario->enrolment->dayQuestions()->pluck('question_id')->sort()->values())
        // ...but a different grouping, which is the point of a second cycle.
        ->and($cycleTwoDayOne)->not->toBe($cycleOne);
});

it('is idempotent — running twice does not duplicate the deal', function (): void {
    $scenario = QuizScenario::make(days: 3, perDay: 3);

    ($this->assign)($scenario->enrolment);
    ($this->assign)($scenario->enrolment->refresh());

    expect($scenario->enrolment->dayQuestions()->count())->toBe(9);
});

it('refuses to deal when the pool is short', function (): void {
    $scenario = QuizScenario::make(days: 5, perDay: 4);

    // Retire 5 questions so only 15 of the 20 needed remain active.
    Question::query()
        ->where('level_id', $scenario->level()->getKey())
        ->limit(5)
        ->update(['status' => ContentStatus::Inactive->value]);

    expect(fn () => ($this->assign)($scenario->enrolment))
        ->toThrow(InsufficientQuestionPoolException::class);

    expect($scenario->enrolment->dayQuestions()->count())->toBe(0);
});

it('deals every student the same hand in fixed_shared mode', function (): void {
    $scenario = QuizScenario::make(days: 4, perDay: 4);
    $scenario->level()->forceFill(['selection_mode' => SelectionMode::FixedShared])->save();

    ($this->assign)($scenario->enrolment);

    $studentB = LevelEnrollment::factory()->create([
        'programme_enrollment_id' => $scenario->programmeEnrollment->getKey(),
        'user_id' => User::factory()->create()->getKey(),
        'level_id' => $scenario->level()->getKey(),
        'cycle_number' => 1,
    ]);
    ($this->assign)($studentB);

    expect($studentB->dayQuestions()->orderBy('day_number')->orderBy('position')->pluck('question_id')->all())
        ->toBe($scenario->enrolment->dayQuestions()->orderBy('day_number')->orderBy('position')->pluck('question_id')->all());
});

it('spreads subjects so no day becomes a single-subject drill', function (): void {
    $scenario = QuizScenario::make(days: 6, perDay: 6);   // 36 questions
    $level = $scenario->level();

    // A pool dominated by one subject is the case that exposes clustering.
    $questions = $level->questions()->get();
    foreach ($questions as $index => $question) {
        $question->forceFill([
            'topic' => $index < 18 ? 'Anatomy' : ($index < 27 ? 'Pathology' : 'Pharmacology'),
        ])->save();
    }

    ($this->assign)($scenario->enrolment);

    // Anatomy is 18 of 36, so the best any scheme can do is 18/6 = 3 per day.
    // Asserting that achievable bound is what makes this test meaningful: a naive
    // fill would produce 2,2,2,2,4,6.
    $expectedMax = (int) ceil(18 / 6);

    foreach (range(1, 6) as $day) {
        $topics = $scenario->enrolment->dayQuestions()
            ->where('day_number', $day)
            ->with('question:id,topic')
            ->get()
            ->map(fn ($row) => $row->question->topic);

        expect(collect($topics)->countBy()->max())
            ->toBeLessThanOrEqual($expectedMax, "day {$day} was dominated by one subject")
            ->and(collect($topics)->unique()->count())
            ->toBeGreaterThan(1, "day {$day} had only one subject");
    }
});

it('handles a realistic 30 x 10 level in one bulk insert', function (): void {
    $scenario = QuizScenario::realistic();

    DB::enableQueryLog();
    $count = ($this->assign)($scenario->enrolment);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBe(300)
        ->and($scenario->enrolment->dayQuestions()->count())->toBe(300)
        // Pool read + one insert + enrolment update + transaction statements. The
        // point: not 300 inserts.
        ->and($queries)->toBeLessThanOrEqual(8, "assignment used {$queries} queries");
});
