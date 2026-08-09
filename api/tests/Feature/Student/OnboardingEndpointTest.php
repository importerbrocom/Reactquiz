<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\EnrollmentDayQuestion;
use App\Models\ExamCategory;
use App\Models\LevelEnrollment;
use App\Models\ProgrammeEnrollment;
use Illuminate\Testing\TestResponse;
use Tests\Support\QuizScenario;

/**
 * First run: choose an exam, choose a programme, get enrolled with day 1 ready.
 *
 * The exam list is data, not code — the client wants to add exam types himself.
 */
beforeEach(function (): void {
    $this->scenario = QuizScenario::make(days: 3, perDay: 3);
    $this->newStudent = student(['timezone' => 'Asia/Kolkata']);
    actingAsStudent($this->newStudent);
});

function onboard(array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/student/onboarding', [
        'exam_category_id' => test()->scenario->category->getKey(),
        'programme_id' => test()->scenario->programme->getKey(),
        ...$overrides,
    ]);
}

// -------------------------------------------------------------------- options ----

it('offers the exams an admin has published', function (): void {
    $response = $this->getJson('/api/v1/student/onboarding');

    $response->assertOk()
        ->assertJsonPath('data.completed', false)
        ->assertJsonPath('data.step', 'welcome')
        ->assertJsonPath('data.exam_categories.0.title', $this->scenario->category->title)
        ->assertJsonPath('data.exam_categories.0.programmes.0.id', $this->scenario->programme->getKey());
});

it('hides an exam that is not published', function (): void {
    ExamCategory::query()->update(['status' => ContentStatus::Draft]);

    $this->getJson('/api/v1/student/onboarding')
        ->assertOk()
        ->assertJsonCount(0, 'data.exam_categories');
});

// ------------------------------------------------------------------ enrolling ----

it('enrols the student and deals their questions', function (): void {
    onboard()->assertCreated()
        ->assertJsonPath('data.current_level', 1)
        ->assertJsonPath('data.current_cycle', 1);

    $enrolment = LevelEnrollment::query()->where('user_id', $this->newStudent->getKey())->firstOrFail();

    expect($enrolment->questionsAreAssigned())->toBeTrue()
        // 3 days x 3 questions, dealt up front so day 1 is ready immediately.
        ->and(EnrollmentDayQuestion::query()->where('level_enrollment_id', $enrolment->getKey())->count())->toBe(9);
});

it('opens day 1 straight after onboarding', function (): void {
    onboard()->assertCreated();

    $this->postJson('/api/v1/student/days/1/attempt')
        ->assertOk()
        ->assertJsonCount(3, 'data.questions');
});

it('stores the timezone on the student, because streaks depend on it', function (): void {
    onboard(['timezone' => 'Asia/Tashkent'])->assertCreated();

    expect($this->newStudent->refresh()->timezone)->toBe('Asia/Tashkent');
});

it('does not enrol the student twice when the request is retried', function (): void {
    // A flaky connection resending onboarding must not re-deal the month.
    onboard()->assertCreated();
    $enrolment = LevelEnrollment::query()->where('user_id', $this->newStudent->getKey())->firstOrFail();
    $dealt = EnrollmentDayQuestion::query()
        ->where('level_enrollment_id', $enrolment->getKey())
        ->pluck('question_id')
        ->all();

    onboard()->assertCreated();

    expect(ProgrammeEnrollment::query()->where('user_id', $this->newStudent->getKey())->count())->toBe(1)
        ->and(LevelEnrollment::query()->where('user_id', $this->newStudent->getKey())->count())->toBe(1)
        ->and(EnrollmentDayQuestion::query()
            ->where('level_enrollment_id', $enrolment->getKey())
            ->pluck('question_id')
            ->all())->toBe($dealt);
});

it('refuses a programme that belongs to another exam', function (): void {
    // Otherwise a client could pair "FMGE" with an AMC programme.
    $otherExam = QuizScenario::make(days: 1, perDay: 1);

    onboard(['programme_id' => $otherExam->programme->getKey()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('programme_id');
});

it('rejects an unpublished programme', function (): void {
    $this->scenario->programme->forceFill(['status' => ContentStatus::Draft])->save();

    onboard()->assertStatus(422)->assertJsonValidationErrors('programme_id');
});

it('rejects a nonsense timezone', function (): void {
    onboard(['timezone' => 'Mars/Olympus_Mons'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('timezone');
});

// ------------------------------------------------------------- before onboarding ----

it('tells a student who has not onboarded what to do', function (): void {
    $response = $this->getJson('/api/v1/student/dashboard');

    // A distinct code from ENROLMENT_INACTIVE: the client sends this student to
    // onboarding, whereas an inactive enrolment is a support problem.
    $response->assertStatus(403)
        ->assertJsonPath('errors.code', 'ONBOARDING_REQUIRED')
        ->assertJsonPath('meta.onboarding_required', true);
});

it('marks onboarding as complete afterwards', function (): void {
    onboard()->assertCreated();

    $this->getJson('/api/v1/student/onboarding')
        ->assertOk()
        ->assertJsonPath('data.completed', true)
        ->assertJsonPath('data.step', 'complete');
});
