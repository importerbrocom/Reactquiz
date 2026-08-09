<?php

declare(strict_types=1);

namespace App\Actions\Quiz;

use App\Enums\AttemptStatus;
use App\Enums\QuestionState;
use App\Models\DailyQuiz;
use App\Models\LevelEnrollment;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptQuestion;
use App\Services\Quiz\QuizUnlockService;
use Illuminate\Support\Facades\DB;

/**
 * Opens (or resumes) a day's quiz.
 *
 * Resuming is the default and the interesting case. A student on a phone will lose the
 * connection, close the tab, or swap devices mid-day; every one of those must land back
 * on the same attempt with the same questions and the same progress. So an in-progress
 * attempt is always reused, and a new one is created only when there is none — which
 * also means two devices opening day 7 at once cannot produce two attempts, because the
 * enrolment row is locked while we look.
 *
 * Re-taking a day that is already finished is allowed and unlimited (the student asked
 * for that explicitly): it creates a fresh attempt with a new attempt_number, which
 * re-shuffles the option order, and it cannot un-complete the day, because completion is
 * derived from the existence of a full-marks attempt rather than from a flag that a new
 * attempt could overwrite.
 */
final readonly class StartQuizAttemptAction
{
    public function __construct(
        private QuizUnlockService $unlock,
        private AssignLevelQuestionsAction $assign,
    ) {}

    public function __invoke(LevelEnrollment $enrolment, int $day): QuizAttempt
    {
        $this->unlock->assertDayUnlocked($enrolment, $day);

        // First visit ever: deal this student's 300 questions into their 30 days.
        // Idempotent, so a concurrent second call is harmless.
        if (! $enrolment->questionsAreAssigned()) {
            ($this->assign)($enrolment);
            $enrolment->refresh();
        }

        return DB::transaction(function () use ($enrolment, $day): QuizAttempt {
            /** @var LevelEnrollment $locked */
            $locked = LevelEnrollment::query()
                ->whereKey($enrolment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /** @var DailyQuiz $dailyQuiz */
            $dailyQuiz = DailyQuiz::query()
                ->where('level_id', $locked->level_id)
                ->where('day_number', $day)
                ->firstOrFail();

            $open = QuizAttempt::query()
                ->where('level_enrollment_id', $locked->getKey())
                ->where('daily_quiz_id', $dailyQuiz->getKey())
                ->where('cycle_number', $locked->cycle_number)
                ->where('status', AttemptStatus::InProgress->value)
                ->latest('id')
                ->first();

            if ($open !== null) {
                return $open;
            }

            return $this->create($locked, $dailyQuiz, $day);
        });
    }

    private function create(LevelEnrollment $enrolment, DailyQuiz $dailyQuiz, int $day): QuizAttempt
    {
        $level = $enrolment->level;

        $attemptNumber = (int) QuizAttempt::query()
            ->where('level_enrollment_id', $enrolment->getKey())
            ->where('daily_quiz_id', $dailyQuiz->getKey())
            ->where('cycle_number', $enrolment->cycle_number)
            ->max('attempt_number') + 1;

        $attempt = new QuizAttempt;
        $attempt->forceFill([
            'user_id' => $enrolment->user_id,
            'level_enrollment_id' => $enrolment->getKey(),
            'level_id' => $enrolment->level_id,
            'daily_quiz_id' => $dailyQuiz->getKey(),
            'day_number' => $day,
            'cycle_number' => $enrolment->cycle_number,
            'attempt_number' => $attemptNumber,
            'status' => AttemptStatus::InProgress,
            // Snapshotted, so an admin changing the level's daily count tomorrow cannot
            // move the goalposts for an attempt already in flight.
            'required_count' => $level->daily_question_count,
            'current_position' => 1,
            'started_at' => now(),
            'last_activity_at' => now(),
        ])->save();

        $this->seedQuestionStates($attempt, $enrolment, $day);

        if ($enrolment->started_at === null) {
            $enrolment->forceFill(['started_at' => now()])->save();
        }

        // Reloaded so the caller sees the database's defaults (mastered_count,
        // wrong_submissions, ...) as 0 rather than null. Without this, the very first
        // payload a student receives reports a null progress counter.
        return $attempt->refresh();
    }

    /**
     * Copy this student's assignment for the day into per-attempt state rows.
     *
     * One bulk insert. These rows are what make "answer all ten correctly" checkable
     * without trusting anything the client sends, and the question ids come from the
     * persisted assignment, so a retake presents exactly the same ten questions.
     */
    private function seedQuestionStates(QuizAttempt $attempt, LevelEnrollment $enrolment, int $day): void
    {
        $questionIds = $enrolment->dayQuestions()
            ->where('day_number', $day)
            ->orderBy('position')
            ->pluck('question_id');

        $now = now();
        $rows = [];

        foreach ($questionIds as $index => $questionId) {
            $rows[] = [
                'quiz_attempt_id' => $attempt->getKey(),
                'question_id' => $questionId,
                'position' => $index + 1,
                'state' => QuestionState::Unanswered->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            QuizAttemptQuestion::query()->insert($rows);
        }
    }
}
