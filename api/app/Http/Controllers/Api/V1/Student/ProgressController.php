<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentQuestionProgress;
use App\Services\Student\StudentContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Progress and accuracy, including the one metric that says whether the product works.
 */
final class ProgressController extends Controller
{
    public function __construct(
        private readonly StudentContext $context,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $programmeEnrolment = $this->context->programmeEnrolment($user);
        $enrolment = $this->context->currentLevelEnrolment($user);

        return ApiResponse::success([
            'level' => [
                'number' => $enrolment->level->level_number,
                'cycle' => $enrolment->cycle_number,
                'completed_days' => $enrolment->completed_days,
                'total_days' => $enrolment->level->total_quiz_days,
                'percent' => (float) $enrolment->progress_percent,
                'study_time_seconds' => $enrolment->total_study_seconds,
            ],
            'programme' => [
                'levels_completed' => $programmeEnrolment->total_levels_completed,
                'total_levels' => $programmeEnrolment->programme->total_levels,
                'current_cycle' => $programmeEnrolment->current_cycle,
                'percent' => (float) $programmeEnrolment->overall_progress_percent,
            ],
            'accuracy' => $this->accuracy($user->getKey(), $enrolment->level_id),
            'retention' => $this->retention($user->getKey(), $enrolment->level_id, $enrolment->cycle_number),
            'by_topic' => $this->byTopic($user->getKey(), $enrolment->level_id),
        ]);
    }

    /**
     * Lifetime accuracy for this level.
     *
     * One aggregate over the student's own progress rows, which is a small, indexed set
     * (at most 300 rows per level) rather than a scan of every submission they ever made.
     *
     * @return array<string, mixed>
     */
    private function accuracy(int $userId, int $levelId): array
    {
        /** @var object{questions: int|null, mastered: int|null, attempts: int|null, correct: int|null, first_time: int|null} $row */
        $row = DB::table('student_question_progress')
            ->where('user_id', $userId)
            ->where('level_id', $levelId)
            ->selectRaw('count(*) as questions')
            ->selectRaw('sum(case when is_mastered = 1 then 1 else 0 end) as mastered')
            ->selectRaw('sum(attempts) as attempts')
            ->selectRaw('sum(correct_count) as correct')
            ->selectRaw('sum(case when first_attempt_correct = 1 then 1 else 0 end) as first_time')
            ->first();

        $questions = (int) ($row->questions ?? 0);
        $attempts = (int) ($row->attempts ?? 0);

        return [
            'questions_seen' => $questions,
            'questions_mastered' => (int) ($row->mastered ?? 0),
            'total_submissions' => $attempts,
            'submission_accuracy_percent' => $attempts === 0
                ? null
                : round((int) ($row->correct ?? 0) / $attempts * 100, 2),
            // The honest measure: right on the FIRST look, before any retry.
            'first_attempt_accuracy_percent' => $questions === 0
                ? null
                : round((int) ($row->first_time ?? 0) / $questions * 100, 2),
        ];
    }

    /**
     * Whether the student is actually learning, or merely memorising.
     *
     * Compares first-attempt accuracy in this cycle against the previous one for the
     * SAME questions. If cycle 2 is not better than cycle 1, the product is not
     * teaching, and no amount of streak-count polish would change that. This is the
     * number worth watching.
     *
     * @return array<string, mixed>
     */
    private function retention(int $userId, int $levelId, int $cycle): array
    {
        if ($cycle < 2) {
            return [
                'available' => false,
                'reason' => 'Retention is measured from the second cycle onwards.',
            ];
        }

        $rows = StudentQuestionProgress::query()
            ->where('user_id', $userId)
            ->where('level_id', $levelId)
            ->whereNotNull('cycle_first_attempt')
            ->get(['question_id', 'cycle_first_attempt']);

        $previous = $rows->filter(fn (StudentQuestionProgress $p): bool => $p->firstAttemptCorrectInCycle($cycle - 1) !== null);
        $current = $rows->filter(fn (StudentQuestionProgress $p): bool => $p->firstAttemptCorrectInCycle($cycle) !== null);

        $rate = static fn ($set, int $c): ?float => $set->isEmpty()
            ? null
            : round($set->filter(fn (StudentQuestionProgress $p): bool => $p->firstAttemptCorrectInCycle($c) === true)
                ->count() / $set->count() * 100, 2);

        $before = $rate($previous, $cycle - 1);
        $after = $rate($current, $cycle);

        return [
            'available' => $before !== null && $after !== null,
            'previous_cycle' => $cycle - 1,
            'previous_first_attempt_percent' => $before,
            'current_cycle' => $cycle,
            'current_first_attempt_percent' => $after,
            'change' => $before === null || $after === null ? null : round($after - $before, 2),
        ];
    }

    /**
     * Where the student is weak, by subject.
     *
     * @return array<int, array<string, mixed>>
     */
    private function byTopic(int $userId, int $levelId): array
    {
        return DB::table('student_question_progress')
            ->join('questions', 'questions.id', '=', 'student_question_progress.question_id')
            ->where('student_question_progress.user_id', $userId)
            ->where('student_question_progress.level_id', $levelId)
            ->whereNotNull('questions.topic')
            ->groupBy('questions.topic')
            ->selectRaw('questions.topic')
            ->selectRaw('count(*) as seen')
            ->selectRaw('sum(case when student_question_progress.is_mastered = 1 then 1 else 0 end) as mastered')
            ->selectRaw('sum(student_question_progress.wrong_count) as wrong')
            ->orderByDesc('wrong')
            ->get()
            ->map(fn (object $row): array => [
                'topic' => $row->topic,
                'seen' => (int) $row->seen,
                'mastered' => (int) $row->mastered,
                'wrong_answers' => (int) $row->wrong,
            ])
            ->all();
    }
}
