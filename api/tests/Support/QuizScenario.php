<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\AttemptStatus;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\QuestionState;
use App\Enums\UserRole;
use App\Models\DailyQuiz;
use App\Models\ExamCategory;
use App\Models\Level;
use App\Models\LevelEnrollment;
use App\Models\LevelTest;
use App\Models\Programme;
use App\Models\ProgrammeEnrollment;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptQuestion;
use App\Models\User;

/**
 * Builds a complete, coherent programme for tests.
 *
 * Levels default to a deliberately tiny shape (2 days x 3 questions) so a test can
 * drive a whole month to completion in milliseconds. Anything asserting real-world
 * scale asks for 30 x 10 explicitly.
 */
final class QuizScenario
{
    public ExamCategory $category;

    public Programme $programme;

    /** @var array<int, Level> keyed by level_number */
    public array $levels = [];

    public User $student;

    public ProgrammeEnrollment $programmeEnrollment;

    public LevelEnrollment $enrolment;

    private function __construct(
        private readonly int $totalLevels,
        private readonly int $days,
        private readonly int $perDay,
    ) {}

    public static function make(int $levels = 1, int $days = 2, int $perDay = 3): self
    {
        $scenario = new self($levels, $days, $perDay);
        $scenario->build();

        return $scenario;
    }

    /** A realistic 30-day, 10-per-day level with the full 300-question pool. */
    public static function realistic(int $levels = 1): self
    {
        return self::make($levels, 30, 10);
    }

    private function build(): void
    {
        $this->category = ExamCategory::factory()->create(['status' => ContentStatus::Active]);

        $this->programme = Programme::factory()->create([
            'exam_category_id' => $this->category->getKey(),
            'total_levels' => $this->totalLevels,
            'total_cycles' => 2,
            'status' => ContentStatus::Active,
        ]);

        for ($number = 1; $number <= $this->totalLevels; $number++) {
            $level = Level::factory()->create([
                'programme_id' => $this->programme->getKey(),
                'level_number' => $number,
                'total_quiz_days' => $this->days,
                'daily_question_count' => $this->perDay,
                'test_day' => $this->days + 1,
                'test_question_count' => $this->days * $this->perDay,
                'status' => ContentStatus::Active,
            ]);

            $this->seedLevelContent($level);
            $this->levels[$number] = $level;
        }

        $this->student = User::factory()->create();
        $this->student->assignRole(UserRole::Student->value);

        $this->programmeEnrollment = ProgrammeEnrollment::factory()->create([
            'user_id' => $this->student->getKey(),
            'programme_id' => $this->programme->getKey(),
            'status' => EnrollmentStatus::Active,
            'current_level' => 1,
            'current_cycle' => 1,
        ]);

        $this->enrolment = $this->enrolInLevel(1);
    }

    /** Exactly total_quiz_days x daily_question_count questions, plus day containers. */
    private function seedLevelContent(Level $level): void
    {
        $required = $level->total_quiz_days * $level->daily_question_count;

        Question::factory()
            ->count($required)
            ->forLevel($level)
            ->create();

        for ($day = 1; $day <= $level->total_quiz_days; $day++) {
            DailyQuiz::factory()->create([
                'level_id' => $level->getKey(),
                'day_number' => $day,
                'question_count' => $level->daily_question_count,
                'status' => ContentStatus::Active,
            ]);
        }

        LevelTest::factory()->create([
            'level_id' => $level->getKey(),
            'day_number' => $level->test_day,
            'question_count' => $required,
            'attempt_limit' => 0,
            'status' => ContentStatus::Active,
        ]);

        $level->forceFill(['questions_count' => $required])->save();
    }

    public function enrolInLevel(int $levelNumber, int $cycle = 1): LevelEnrollment
    {
        return LevelEnrollment::factory()->create([
            'programme_enrollment_id' => $this->programmeEnrollment->getKey(),
            'user_id' => $this->student->getKey(),
            'level_id' => $this->levels[$levelNumber]->getKey(),
            'cycle_number' => $cycle,
            'status' => EnrollmentStatus::Active,
        ]);
    }

    public function level(int $number = 1): Level
    {
        return $this->levels[$number];
    }

    public function dailyQuiz(int $day, int $levelNumber = 1): DailyQuiz
    {
        return DailyQuiz::query()
            ->where('level_id', $this->levels[$levelNumber]->getKey())
            ->where('day_number', $day)
            ->firstOrFail();
    }

    /**
     * Record a day as finished at full marks, without going through the API.
     *
     * Used to set up "the student is on day 7" states. Deliberately writes the same
     * shape the real completion action produces, so unlock logic under test sees
     * realistic data.
     */
    public function completeDay(int $day, ?LevelEnrollment $enrolment = null, ?int $score = null): QuizAttempt
    {
        $enrolment ??= $this->enrolment;
        $level = $enrolment->level;
        $required = $level->daily_question_count;
        $score ??= $required;

        $attempt = QuizAttempt::factory()->create([
            'user_id' => $enrolment->user_id,
            'level_enrollment_id' => $enrolment->getKey(),
            'level_id' => $level->getKey(),
            'daily_quiz_id' => $this->dailyQuiz($day, $level->level_number)->getKey(),
            'day_number' => $day,
            'cycle_number' => $enrolment->cycle_number,
            'required_count' => $required,
            'mastered_count' => $score,
            'score' => $score,
            'status' => AttemptStatus::Completed,
            'completed_at' => now(),
        ]);

        $enrolment->forceFill([
            'completed_days' => $enrolment->completed_days + 1,
            'last_completed_day' => $day,
            'last_completed_at' => now(),
            'current_day' => min($day + 1, $level->test_day),
            'highest_unlocked_day' => min($day + 1, $level->total_quiz_days),
        ])->save();

        return $attempt;
    }

    public function completeDaysUpTo(int $day, ?LevelEnrollment $enrolment = null): void
    {
        for ($d = 1; $d <= $day; $d++) {
            $this->completeDay($d, $enrolment);
        }
    }

    /** Finish the whole level, which is what makes the month-end test eligible. */
    public function completeAllDays(?LevelEnrollment $enrolment = null): void
    {
        $enrolment ??= $this->enrolment;

        $this->completeDaysUpTo($enrolment->level->total_quiz_days, $enrolment);

        $enrolment->forceFill(['test_unlocked_at' => now()])->save();
    }

    public function levelTest(int $levelNumber = 1): LevelTest
    {
        return LevelTest::query()
            ->where('level_id', $this->levels[$levelNumber]->getKey())
            ->firstOrFail();
    }

    /** An in-progress attempt with its per-question state rows pre-created. */
    public function startAttempt(int $day, ?LevelEnrollment $enrolment = null): QuizAttempt
    {
        $enrolment ??= $this->enrolment;
        $level = $enrolment->level;

        $attempt = QuizAttempt::factory()->create([
            'user_id' => $enrolment->user_id,
            'level_enrollment_id' => $enrolment->getKey(),
            'level_id' => $level->getKey(),
            'daily_quiz_id' => $this->dailyQuiz($day, $level->level_number)->getKey(),
            'day_number' => $day,
            'cycle_number' => $enrolment->cycle_number,
            'required_count' => $level->daily_question_count,
            'status' => AttemptStatus::InProgress,
        ]);

        $questionIds = $enrolment->dayQuestions()
            ->where('day_number', $day)
            ->orderBy('position')
            ->pluck('question_id');

        foreach ($questionIds as $index => $questionId) {
            QuizAttemptQuestion::query()->create([
                'quiz_attempt_id' => $attempt->getKey(),
                'question_id' => $questionId,
                'position' => $index + 1,
                'state' => QuestionState::Unanswered,
            ]);
        }

        return $attempt;
    }
}
