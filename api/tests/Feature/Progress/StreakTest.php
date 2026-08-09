<?php

declare(strict_types=1);

use App\Models\StudentStreak;
use App\Services\Progress\StreakService;
use Carbon\CarbonImmutable;
use Tests\Support\QuizScenario;

/**
 * Streaks are counted in the STUDENT's local dates, not the server's.
 *
 * The app runs in UTC but the students are in India. If streaks rolled over on server
 * dates, a study session at 23:00 IST and another at 01:00 IST the next night would
 * land on the same UTC date and read as one day — quietly destroying the streak of
 * exactly the late-evening learners this product is for.
 */
beforeEach(function (): void {
    $this->streaks = app(StreakService::class);
    $this->scenario = QuizScenario::make(days: 2, perDay: 3);
});

function registerAt(string $utc, $scenario, $streaks)
{
    test()->travelTo(CarbonImmutable::parse($utc, 'UTC'));

    return $streaks->registerCompletion($scenario->enrolment->refresh());
}

it('counts a new local day even when the server date has not changed', function (): void {
    $this->scenario->student->forceFill(['timezone' => 'Asia/Kolkata'])->save();

    // 18:00 UTC = 23:30 IST on the 2nd.
    $first = registerAt('2026-08-02 18:00', $this->scenario, $this->streaks);
    // 19:00 UTC, still the 2nd in UTC, but already 00:30 on the 3rd in IST.
    $second = registerAt('2026-08-02 19:00', $this->scenario, $this->streaks);

    expect($first->current_streak)->toBe(1)
        ->and($second->current_streak)->toBe(2);
});

it('counts one local day even when the server date has changed', function (): void {
    $this->scenario->student->forceFill(['timezone' => 'America/New_York'])->save();

    // 23:00 UTC on the 2nd = 19:00 on the 2nd in New York.
    $first = registerAt('2026-08-02 23:00', $this->scenario, $this->streaks);
    // 01:00 UTC on the 3rd = 21:00, still the 2nd in New York. One evening of study.
    $second = registerAt('2026-08-03 01:00', $this->scenario, $this->streaks);

    expect($first->current_streak)->toBe(1)
        ->and($second->current_streak)->toBe(1);
});

it('extends across consecutive local days', function (): void {
    $this->scenario->student->forceFill(['timezone' => 'Asia/Kolkata'])->save();

    registerAt('2026-08-02 12:00', $this->scenario, $this->streaks);
    registerAt('2026-08-03 12:00', $this->scenario, $this->streaks);
    $third = registerAt('2026-08-04 12:00', $this->scenario, $this->streaks);

    expect($third->current_streak)->toBe(3)
        ->and($third->longest_streak)->toBe(3);
});

it('resets after a missed day but remembers the best run', function (): void {
    $this->scenario->student->forceFill(['timezone' => 'Asia/Kolkata'])->save();

    registerAt('2026-08-02 12:00', $this->scenario, $this->streaks);
    registerAt('2026-08-03 12:00', $this->scenario, $this->streaks);
    registerAt('2026-08-04 12:00', $this->scenario, $this->streaks);

    $afterGap = registerAt('2026-08-06 12:00', $this->scenario, $this->streaks);

    expect($afterGap->current_streak)->toBe(1)
        ->and($afterGap->longest_streak)->toBe(3);
});

it('stamps the activity date in the student local calendar', function (): void {
    // UTC+14: midday on the 2nd in UTC is already the 3rd for this student.
    $this->scenario->student->forceFill(['timezone' => 'Pacific/Kiritimati'])->save();

    $streak = registerAt('2026-08-02 12:00', $this->scenario, $this->streaks);

    expect($streak->last_activity_date->toDateString())->toBe('2026-08-03');
});

it('stamps behind the server date for a student west of UTC', function (): void {
    // UTC-11: 06:00 on the 2nd in UTC is still the 1st for this student.
    $this->scenario->student->forceFill(['timezone' => 'Pacific/Niue'])->save();

    $streak = registerAt('2026-08-02 06:00', $this->scenario, $this->streaks);

    expect($streak->last_activity_date->toDateString())->toBe('2026-08-01');
});

it('keeps one streak row per programme', function (): void {
    registerAt('2026-08-02 12:00', $this->scenario, $this->streaks);
    registerAt('2026-08-03 12:00', $this->scenario, $this->streaks);

    expect(StudentStreak::query()
        ->where('user_id', $this->scenario->student->getKey())
        ->count())->toBe(1);
});
