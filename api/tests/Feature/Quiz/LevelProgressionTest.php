<?php

declare(strict_types=1);

use App\Actions\LevelTest\StartLevelTestAction;
use App\Actions\LevelTest\SubmitLevelTestAction;
use App\Actions\LevelTest\SyncLevelTestAnswersAction;
use App\Actions\Quiz\AdvanceLevelAction;
use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\DTOs\Quiz\TestAnswerData;
use App\Enums\EnrollmentStatus;
use App\Enums\OptionKey;
use App\Enums\UnlockMode;
use App\Events\LevelAdvanced;
use App\Exceptions\LevelAdvanceNotAllowedException;
use App\Models\EnrollmentDayQuestion;
use App\Models\LevelEnrollment;
use App\Models\Programme;
use App\Models\Question;
use App\Services\Quiz\LevelProgressionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\QuizScenario;

/**
 * Six levels make a cycle; the cycle then repeats over the same questions with fresh
 * per-student seeds (docs/adr/002).
 */
beforeEach(function (): void {
    $this->advance = app(AdvanceLevelAction::class);
    $this->progression = app(LevelProgressionService::class);

    $this->scenario = QuizScenario::make(levels: 3, days: 2, perDay: 2);
    app(AssignLevelQuestionsAction::class)($this->scenario->enrolment);
});

/** Finish every day and sit the month-end test. */
function finishLevel(LevelEnrollment $enrolment, QuizScenario $scenario, bool $pass = true): void
{
    if (! $enrolment->questionsAreAssigned()) {
        app(AssignLevelQuestionsAction::class)($enrolment);
    }

    $scenario->completeAllDays($enrolment);

    $attempt = app(StartLevelTestAction::class)($enrolment->refresh());
    $questions = Question::query()->whereIn('id', $attempt->question_order)->get()->keyBy('id');

    $answers = collect($attempt->question_order)->map(function (int $id) use ($questions, $pass): TestAnswerData {
        $question = $questions->get($id);

        return new TestAnswerData(
            questionId: $id,
            selectedOption: $pass
                ? $question->correct_option
                : collect(OptionKey::cases())
                    ->first(fn ($k): bool => $k !== $question->correct_option
                        && in_array($k->value, ['a', 'b', 'c', 'd'], true)),
        );
    })->all();

    foreach (array_chunk($answers, 20) as $chunk) {
        app(SyncLevelTestAnswersAction::class)($attempt->refresh(), $chunk, (string) Str::uuid7());
    }

    app(SubmitLevelTestAction::class)($attempt->refresh());
}

// -------------------------------------------------------------- eligibility ----

it('refuses to advance while days are outstanding', function (): void {
    $this->scenario->completeDay(1);

    $decision = $this->progression->decide($this->scenario->enrolment->refresh());

    expect($decision->denied())->toBeTrue()
        ->and($decision->reason)->toBe('days_outstanding')
        ->and($decision->context['completed_days'])->toBe(1);
});

it('refuses to advance before the month-end test is sat', function (): void {
    // Thirty days of practice that is never examined is not a finished month.
    $this->scenario->completeAllDays();

    $decision = $this->progression->decide($this->scenario->enrolment->refresh());

    expect($decision->denied())->toBeTrue()
        ->and($decision->reason)->toBe('test_not_taken');
});

it('advances on a failed test by default', function (): void {
    // The test measures retention; it is not a trap that strands a student on level 1.
    finishLevel($this->scenario->enrolment, $this->scenario, pass: false);

    $decision = $this->progression->decide($this->scenario->enrolment->refresh());

    expect($decision->allowed)->toBeTrue()
        ->and($decision->targetLevelNumber)->toBe(2);
});

it('requires a pass when the programme says so', function (): void {
    $this->scenario->programme->forceFill(['require_test_pass_to_advance' => true])->save();
    finishLevel($this->scenario->enrolment, $this->scenario, pass: false);

    $decision = $this->progression->decide($this->scenario->enrolment->refresh());

    expect($decision->denied())->toBeTrue()
        ->and($decision->reason)->toBe('test_not_passed')
        ->and($decision->context['pass_percentage'])->toBe(50.0);
});

it('lets a retake unlock the next level', function (): void {
    // Attempts are unlimited, so a required pass is a delay, never a dead end.
    $this->scenario->programme->forceFill(['require_test_pass_to_advance' => true])->save();
    finishLevel($this->scenario->enrolment, $this->scenario, pass: false);

    expect($this->progression->decide($this->scenario->enrolment->refresh())->denied())->toBeTrue();

    finishLevel($this->scenario->enrolment->refresh(), $this->scenario, pass: true);

    expect($this->progression->decide($this->scenario->enrolment->refresh())->allowed)->toBeTrue();
});

// ----------------------------------------------------------------- advancing ----

it('enrols the student in the next level', function (): void {
    finishLevel($this->scenario->enrolment, $this->scenario);

    $next = ($this->advance)($this->scenario->enrolment->refresh());

    expect($next->level_id)->toBe($this->scenario->level(2)->getKey())
        ->and($next->cycle_number)->toBe(1)
        ->and($next->questionsAreAssigned())->toBeTrue()
        ->and($next->dayQuestions()->count())->toBe(4);
});

it('leaves the finished level open for review', function (): void {
    // A student on level 2 must still be able to redo day 1 of level 1.
    finishLevel($this->scenario->enrolment, $this->scenario);

    ($this->advance)($this->scenario->enrolment->refresh());
    $finished = $this->scenario->enrolment->refresh();

    expect($finished->completed_at)->not->toBeNull()
        ->and($finished->status)->toBe(EnrollmentStatus::Active);
});

it('moves the programme pointer', function (): void {
    finishLevel($this->scenario->enrolment, $this->scenario);

    ($this->advance)($this->scenario->enrolment->refresh());
    $programme = $this->scenario->programmeEnrollment->refresh();

    expect($programme->current_level)->toBe(2)
        ->and($programme->current_cycle)->toBe(1)
        ->and($programme->total_levels_completed)->toBe(1)
        ->and((float) $programme->overall_progress_percent)->toBe(33.33);
});

it('is idempotent when the button is pressed twice', function (): void {
    Event::fake([LevelAdvanced::class]);
    finishLevel($this->scenario->enrolment, $this->scenario);

    $first = ($this->advance)($this->scenario->enrolment->refresh());
    $second = ($this->advance)($this->scenario->enrolment->refresh());

    expect($second->getKey())->toBe($first->getKey())
        ->and(LevelEnrollment::query()->count())->toBe(2);

    // The crucial part: one deal of questions, not two.
    expect(EnrollmentDayQuestion::query()->where('level_enrollment_id', $first->getKey())->count())->toBe(4);
    Event::assertDispatchedTimes(LevelAdvanced::class, 1);
});

it('refuses to advance when the level is not finished', function (): void {
    expect(fn () => ($this->advance)($this->scenario->enrolment))
        ->toThrow(LevelAdvanceNotAllowedException::class);

    expect(LevelEnrollment::query()->count())->toBe(1);
});

// -------------------------------------------------------------------- cycles ----

it('starts a new cycle after the last level', function (): void {
    // The same questions come round again, which is what the client asked for.
    $enrolment = $this->scenario->enrolment;

    foreach ([1, 2, 3] as $levelNumber) {
        finishLevel($enrolment->refresh(), $this->scenario);

        if ($levelNumber < 3) {
            $enrolment = ($this->advance)($enrolment->refresh());
        }
    }

    $decision = $this->progression->decide($enrolment->refresh());

    expect($decision->allowed)->toBeTrue()
        ->and($decision->targetLevelNumber)->toBe(1)
        ->and($decision->targetCycle)->toBe(2);
});

it('re-deals the same questions differently in cycle 2', function (): void {
    // Cycle 2 is the same 1,800 questions with a new seed — a genuinely new 30 days,
    // for no extra content and almost no extra code.
    $scenario = QuizScenario::make(levels: 1, days: 6, perDay: 5);
    finishLevel($scenario->enrolment, $scenario);
    $scenario->programme->forceFill(['total_levels' => 1, 'total_cycles' => 2])->save();

    $cycleTwo = ($this->advance)($scenario->enrolment->refresh());

    $before = $scenario->enrolment->dayQuestions()
        ->orderBy('day_number')->orderBy('position')->pluck('question_id')->all();
    $after = $cycleTwo->dayQuestions()
        ->orderBy('day_number')->orderBy('position')->pluck('question_id')->all();

    expect($cycleTwo->cycle_number)->toBe(2)
        ->and($after)->not->toBe($before)
        // Same pool, so the same 30 questions — grouped into different days.
        ->and(collect($after)->sort()->values()->all())->toBe(collect($before)->sort()->values()->all())
        ->and($cycleTwo->assignment_seed)->not->toBe($scenario->enrolment->assignment_seed);
});

it('resets the per-cycle counter when a new cycle starts', function (): void {
    $scenario = QuizScenario::make(levels: 1, days: 2, perDay: 2);
    $scenario->programme->forceFill(['total_levels' => 1, 'total_cycles' => 3])->save();
    finishLevel($scenario->enrolment, $scenario);

    ($this->advance)($scenario->enrolment->refresh());
    $programme = $scenario->programmeEnrollment->refresh();

    expect($programme->current_cycle)->toBe(2)
        ->and($programme->current_level)->toBe(1)
        ->and($programme->levels_completed_this_cycle)->toBe(0)
        // Lifetime count keeps climbing across cycles.
        ->and($programme->total_levels_completed)->toBe(1);
});

it('reports the programme as finished after the last cycle', function (): void {
    $scenario = QuizScenario::make(levels: 1, days: 2, perDay: 2);
    $scenario->programme->forceFill(['total_levels' => 1, 'total_cycles' => 1])->save();
    finishLevel($scenario->enrolment, $scenario);

    $decision = $this->progression->decide($scenario->enrolment->refresh());

    expect($decision->denied())->toBeTrue()
        ->and($decision->programmeCompleted)->toBeTrue()
        ->and($decision->reason)->toBe('programme_completed');
});

it('repeats indefinitely when total_cycles is zero', function (): void {
    $scenario = QuizScenario::make(levels: 1, days: 2, perDay: 2);
    $scenario->programme->forceFill(['total_levels' => 1, 'total_cycles' => 0])->save();
    finishLevel($scenario->enrolment, $scenario);

    $decision = $this->progression->decide($scenario->enrolment->refresh());

    expect($decision->allowed)->toBeTrue()
        ->and($decision->targetCycle)->toBe(2);
});

it('points at the follow-on programme when one is configured', function (): void {
    // "After that second pass, a new question bank is introduced."
    $scenario = QuizScenario::make(levels: 1, days: 2, perDay: 2);
    $successor = Programme::factory()->create([
        'exam_category_id' => $scenario->category->getKey(),
    ]);
    $scenario->programme->forceFill([
        'total_levels' => 1,
        'total_cycles' => 1,
        'next_programme_id' => $successor->getKey(),
    ])->save();
    finishLevel($scenario->enrolment, $scenario);

    $decision = $this->progression->decide($scenario->enrolment->refresh());

    expect($decision->programmeCompleted)->toBeTrue()
        ->and($decision->nextProgrammeId)->toBe($successor->getKey());
});

// ------------------------------------------------------------- unlock timing ----

it('can hold the next level until tomorrow', function (): void {
    $this->scenario->programme->forceFill([
        'level_unlock_mode' => UnlockMode::NextCalendarDay,
    ])->save();
    finishLevel($this->scenario->enrolment, $this->scenario);

    $decision = $this->progression->decide($this->scenario->enrolment->refresh());

    expect($decision->allowed)->toBeTrue()
        ->and($decision->unlocksAt)->not->toBeNull();

    expect(fn () => ($this->advance)($this->scenario->enrolment->refresh()))
        ->toThrow(LevelAdvanceNotAllowedException::class);
});

it('opens the next level once tomorrow arrives', function (): void {
    $this->scenario->programme->forceFill([
        'level_unlock_mode' => UnlockMode::NextCalendarDay,
    ])->save();
    finishLevel($this->scenario->enrolment, $this->scenario);

    $this->travel(1)->days();

    expect(($this->advance)($this->scenario->enrolment->refresh())->level_id)
        ->toBe($this->scenario->level(2)->getKey());
});
