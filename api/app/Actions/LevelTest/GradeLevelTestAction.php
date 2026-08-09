<?php

declare(strict_types=1);

namespace App\Actions\LevelTest;

use App\Enums\TestAttemptStatus;
use App\Events\LevelTestGraded;
use App\Models\LevelTestAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Grades a whole month-end test in a fixed number of statements.
 *
 * A 300-question test graded question-by-question would be 300 selects plus 300
 * updates per student. At 500 students finishing the same evening that is 300,000
 * statements, which is how a shared-hosting database dies. So correctness is computed
 * INSIDE the database with one set-based UPDATE that compares each recorded choice
 * against `questions.correct_option`, then one aggregate read, then one write to the
 * attempt. Four statements whether the test has 10 questions or 3,000.
 *
 * A correlated subquery is used rather than an UPDATE...JOIN because the latter is
 * MySQL-specific, and the test suite runs on SQLite.
 */
final readonly class GradeLevelTestAction
{
    public function __invoke(LevelTestAttempt $attempt): LevelTestAttempt
    {
        $graded = DB::transaction(function () use ($attempt): ?LevelTestAttempt {
            /** @var LevelTestAttempt $locked */
            $locked = LevelTestAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotent: a retried job, or a manual re-grade after the queue hiccuped,
            // must not re-stamp graded_at or fire a second event.
            if ($locked->status === TestAttemptStatus::Graded) {
                return null;
            }

            $this->gradeAnswers($locked);
            $totals = $this->tally($locked);

            $test = $locked->levelTest;
            $level = $locked->levelEnrollment->level;

            $passMark = (float) ($test->pass_percentage ?? $level->pass_percentage ?? 50);
            $percentage = $locked->total_questions > 0
                ? round($totals['correct'] / $locked->total_questions * 100, 2)
                : 0.0;
            $passed = $percentage >= $passMark;

            $locked->forceFill([
                'status' => TestAttemptStatus::Graded,
                'correct_count' => $totals['correct'],
                'incorrect_count' => $totals['incorrect'],
                'unanswered_count' => max(0, $locked->total_questions - $totals['answered']),
                'score' => $totals['correct'],
                'percentage' => $percentage,
                'passed' => $passed,
                'graded_at' => now(),
                'result_released_at' => $level->release_results_immediately ? now() : null,
            ])->save();

            if ($passed) {
                $this->recordPass($locked);
            }

            return $locked;
        });

        if ($graded !== null) {
            LevelTestGraded::dispatch($graded->refresh());
        }

        return $graded ?? $attempt->refresh();
    }

    /**
     * One statement, every answer.
     *
     * Rows with no choice are left untouched: `is_correct` stays NULL for them, which
     * distinguishes "wrong" from "never answered" in the review screen. Unanswered is
     * simply not credited — there is no negative marking anywhere in this product.
     */
    private function gradeAnswers(LevelTestAttempt $attempt): void
    {
        $correctOption = '(select correct_option from questions where questions.id = level_test_answers.question_id)';

        DB::update(
            "update level_test_answers
                set is_correct = case when selected_option = {$correctOption} then 1 else 0 end,
                    marks_awarded = case when selected_option = {$correctOption} then 1 else 0 end,
                    updated_at = ?
              where level_test_attempt_id = ?
                and selected_option is not null",
            [now(), $attempt->getKey()],
        );
    }

    /** @return array{correct: int, incorrect: int, answered: int} */
    private function tally(LevelTestAttempt $attempt): array
    {
        /** @var object{correct: int|null, incorrect: int|null, answered: int|null} $row */
        $row = DB::selectOne(
            'select
                sum(case when is_correct = 1 then 1 else 0 end) as correct,
                sum(case when is_correct = 0 then 1 else 0 end) as incorrect,
                sum(case when selected_option is not null then 1 else 0 end) as answered
             from level_test_answers
             where level_test_attempt_id = ?',
            [$attempt->getKey()],
        );

        return [
            'correct' => (int) ($row->correct ?? 0),
            'incorrect' => (int) ($row->incorrect ?? 0),
            'answered' => (int) ($row->answered ?? 0),
        ];
    }

    /**
     * Stamp the pass on the enrolment, once.
     *
     * Kept as the earliest passing attempt so a later failed retake — students retake
     * freely to practise — can never revoke a pass already earned.
     */
    private function recordPass(LevelTestAttempt $attempt): void
    {
        $enrolment = $attempt->levelEnrollment;

        if ($enrolment->test_passed_at !== null) {
            return;
        }

        // Deliberately no denormalised "best percentage" column: retakes are unlimited,
        // so the best score is a MAX over a handful of attempt rows, and a cached copy
        // would be one more thing that can silently disagree with them.
        $enrolment->forceFill(['test_passed_at' => now()])->save();
    }
}
