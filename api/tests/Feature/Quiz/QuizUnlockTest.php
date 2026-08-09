<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UnlockMode;
use App\Exceptions\LevelTestNotEligibleException;
use App\Exceptions\QuizDayLockedException;
use App\Models\LevelTestAttempt;
use App\Services\Quiz\QuizUnlockService;
use Tests\Support\QuizScenario;

/**
 * Business rules 3, 10, 11, 12 and 16.
 *
 * These are the rules the whole product rests on: a student cannot skip a day, and
 * the month-end test opens only after every day is finished at full marks. If any of
 * these assertions ever fail, the learning model is broken.
 */
beforeEach(function (): void {
    $this->unlock = app(QuizUnlockService::class);
    // 5 days x 4 questions: enough to exercise sequencing without being slow.
    $this->scenario = QuizScenario::make(days: 5, perDay: 4);
    $this->enrolment = $this->scenario->enrolment;
});

// ------------------------------------------------------------------ day 1 ----

it('makes day 1 available immediately', function (): void {
    expect($this->unlock->decideDay($this->enrolment, 1)->allowed)->toBeTrue();
});

it('locks every day beyond the first for a brand new student', function (int $day): void {
    $decision = $this->unlock->decideDay($this->enrolment, $day);

    expect($decision->allowed)->toBeFalse()
        ->and($decision->reason)->toBe('previous_day_incomplete')
        ->and($decision->blockingDay)->toBe($day - 1);
})->with([2, 3, 4, 5]);

// ------------------------------------------------- sequential progression ----

it('unlocks the next day only after the previous is finished at full marks', function (): void {
    $this->scenario->completeDay(1);

    expect($this->unlock->decideDay($this->enrolment->refresh(), 2)->allowed)->toBeTrue()
        ->and($this->unlock->decideDay($this->enrolment, 3)->allowed)->toBeFalse();
});

it('refuses to unlock the next day on a partial score', function (): void {
    // 3 of 4 correct is not completion — rule 9 requires full marks.
    $this->scenario->completeDay(1, score: 3);

    $decision = $this->unlock->decideDay($this->enrolment->refresh(), 2);

    expect($decision->allowed)->toBeFalse()
        ->and($decision->reason)->toBe('previous_day_incomplete');
});

it('opens each day in turn as the student progresses', function (): void {
    foreach (range(1, 5) as $day) {
        expect($this->unlock->decideDay($this->enrolment->refresh(), $day)->allowed)
            ->toBeTrue("day {$day} should be open");

        // Nothing beyond the current day is reachable.
        if ($day < 5) {
            expect($this->unlock->decideDay($this->enrolment, $day + 2)->allowed)->toBeFalse();
        }

        $this->scenario->completeDay($day);
    }
});

it('cannot be tricked by a corrupted highest_unlocked_day counter', function (): void {
    // The cached counter is a convenience for reads; access is always re-derived
    // from completion records, so tampering with it grants nothing.
    $this->enrolment->forceFill(['highest_unlocked_day' => 5, 'completed_days' => 4])->save();

    expect($this->unlock->decideDay($this->enrolment->refresh(), 5)->allowed)->toBeFalse();
});

it('rejects days outside the level', function (int $day): void {
    expect($this->unlock->decideDay($this->enrolment, $day)->reason)->toBe('day_out_of_range');
})->with([0, -1, 99]);

// ------------------------------------------------------------- enrolment ----

it('locks everything when the enrolment is not active', function (): void {
    $this->enrolment->forceFill(['status' => EnrollmentStatus::Paused])->save();

    expect($this->unlock->decideDay($this->enrolment->refresh(), 1)->reason)
        ->toBe('enrolment_inactive');
});

it('locks everything when the level is not published', function (): void {
    $this->scenario->level()->forceFill(['status' => ContentStatus::Draft])->save();

    expect($this->unlock->decideDay($this->enrolment->refresh(), 1)->reason)
        ->toBe('level_inactive');
});

// ---------------------------------------------------------- pacing modes ----

it('holds the next day until tomorrow in next_calendar_day mode', function (): void {
    $this->scenario->level()->forceFill(['unlock_mode' => UnlockMode::NextCalendarDay])->save();
    $this->scenario->completeDay(1);

    $decision = $this->unlock->decideDay($this->enrolment->refresh(), 2);

    expect($decision->allowed)->toBeFalse()
        ->and($decision->reason)->toBe('available_tomorrow')
        ->and($decision->unlocksAt)->not->toBeNull();
});

it('opens the next day once the student local date rolls over', function (): void {
    $this->scenario->level()->forceFill(['unlock_mode' => UnlockMode::NextCalendarDay])->save();
    $this->scenario->completeDay(1);

    $this->travel(1)->day();

    expect($this->unlock->decideDay($this->enrolment->refresh(), 2)->allowed)->toBeTrue();
});

it('uses the student timezone, not the server, to decide the calendar day', function (): void {
    // Completed 22:00 in Kolkata is still the same local day, even though it is
    // already tomorrow in UTC+13.
    $this->scenario->level()->forceFill(['unlock_mode' => UnlockMode::NextCalendarDay])->save();
    $this->scenario->student->forceFill(['timezone' => 'Asia/Kolkata'])->save();

    $this->travelTo(now()->setTimezone('Asia/Kolkata')->setTime(22, 0));
    $this->scenario->completeDay(1);

    expect($this->unlock->decideDay($this->enrolment->refresh(), 2)->allowed)->toBeFalse();
});

it('paces days from the enrolment start in scheduled mode', function (): void {
    $this->scenario->level()->forceFill(['unlock_mode' => UnlockMode::Scheduled])->save();
    $this->enrolment->forceFill(['started_at' => now()])->save();
    $this->scenario->completeDay(1);

    expect($this->unlock->decideDay($this->enrolment->refresh(), 2)->reason)->toBe('scheduled_later');

    $this->travel(1)->day();

    expect($this->unlock->decideDay($this->enrolment->refresh(), 2)->allowed)->toBeTrue();
});

// --------------------------------------------------------- month-end test ----

it('keeps the month-end test locked until every day is done', function (): void {
    $this->scenario->completeDaysUpTo(4);   // one short of 5

    $decision = $this->unlock->decideLevelTest($this->enrolment->refresh());

    expect($decision->allowed)->toBeFalse()
        ->and($decision->reason)->toBe('level_test_not_eligible')
        ->and($decision->context['completed_days'])->toBe(4)
        ->and($decision->context['required_days'])->toBe(5)
        ->and($decision->context['missing_days'])->toBe([5]);
});

it('keeps the test locked when a day was only partially completed', function (): void {
    $this->scenario->completeDaysUpTo(4);
    $this->scenario->completeDay(5, score: 3);       // 3 of 4 — not full marks

    expect($this->unlock->decideLevelTest($this->enrolment->refresh())->allowed)->toBeFalse();
});

it('unlocks the month-end test once all days are at full marks', function (): void {
    $this->scenario->completeDaysUpTo(5);

    expect($this->unlock->decideLevelTest($this->enrolment->refresh())->allowed)->toBeTrue();
});

it('routes the test day number through the test eligibility check', function (): void {
    // Day 6 in a 5-day level IS the test day, not an out-of-range day.
    $decision = $this->unlock->decideDay($this->enrolment, $this->scenario->level()->test_day);

    expect($decision->reason)->toBe('level_test_not_eligible');
});

it('enforces an attempt limit when the level sets one', function (): void {
    $this->scenario->completeDaysUpTo(5);
    $test = $this->scenario->level()->test;
    $test->forceFill(['attempt_limit' => 1])->save();

    LevelTestAttempt::factory()->create([
        'user_id' => $this->scenario->student->getKey(),
        'level_test_id' => $test->getKey(),
        'level_enrollment_id' => $this->enrolment->getKey(),
        'level_id' => $this->scenario->level()->getKey(),
        'cycle_number' => 1,
    ]);

    $decision = $this->unlock->decideLevelTest($this->enrolment->refresh());

    expect($decision->allowed)->toBeFalse()
        ->and($decision->reason)->toBe('attempt_limit_reached');
});

// ------------------------------------------------------------- exceptions ----

it('throws a 423 for a locked day', function (): void {
    expect(fn () => $this->unlock->assertDayUnlocked($this->enrolment, 3))
        ->toThrow(QuizDayLockedException::class);

    $exception = new QuizDayLockedException;
    expect($exception->status())->toBe(423)
        ->and($exception->errorCode())->toBe('QUIZ_DAY_LOCKED');
});

it('throws a 423 for an ineligible month-end test', function (): void {
    expect(fn () => $this->unlock->assertLevelTestUnlocked($this->enrolment))
        ->toThrow(LevelTestNotEligibleException::class);
});

it('does not throw for an open day', function (): void {
    $this->unlock->assertDayUnlocked($this->enrolment, 1);
})->throwsNoExceptions();

// ------------------------------------------------------- timeline & counts ----

it('builds the whole day timeline in a small, constant number of queries', function (): void {
    $this->scenario->completeDaysUpTo(2);
    $this->scenario->startAttempt(3);

    DB::enableQueryLog();
    $states = $this->unlock->dayStates($this->enrolment->refresh());
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($states)->toHaveCount(5)
        ->and(array_column($states, 'status'))->toBe([
            'completed', 'completed', 'in_progress', 'locked', 'locked',
        ]);

    // The point of this test: one query for completions, one for in-progress
    // attempts, plus relation loads — never one query per day.
    expect($queries)->toBeLessThanOrEqual(5, "dayStates used {$queries} queries");
});

it('reports scores and unlock hints on the timeline', function (): void {
    $this->scenario->completeDay(1);
    $states = $this->unlock->dayStates($this->enrolment->refresh());

    expect($states[0]['score'])->toBe(4)
        ->and($states[0]['required'])->toBe(4)
        ->and($states[0]['completed_at'])->toBeString()
        ->and($states[1]['status'])->toBe('available')
        ->and($states[2]['unlock_hint'])->toBeString();
});

it('counts completed days and the highest reachable day', function (): void {
    $this->scenario->completeDaysUpTo(3);
    $enrolment = $this->enrolment->refresh();

    expect($this->unlock->completedDayCount($enrolment))->toBe(3)
        ->and($this->unlock->highestUnlockedDay($enrolment))->toBe(4);
});

it('scopes completion to the current cycle', function (): void {
    // Cycle 2 starts from scratch even though cycle 1 is finished: the same
    // questions must be earned again.
    $this->scenario->completeDaysUpTo(5);

    $cycleTwo = $this->scenario->enrolInLevel(1, cycle: 2);

    expect($this->unlock->completedDayCount($cycleTwo))->toBe(0)
        ->and($this->unlock->decideDay($cycleTwo, 2)->allowed)->toBeFalse()
        ->and($this->unlock->decideDay($cycleTwo, 1)->allowed)->toBeTrue();
});
