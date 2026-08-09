<?php

declare(strict_types=1);

namespace App\Actions\LevelTest;

use App\DTOs\Quiz\TestAnswerData;
use App\DTOs\Quiz\TestSyncResultData;
use App\Exceptions\TestAttemptClosedException;
use App\Exceptions\TestAttemptExpiredException;
use App\Models\LevelTestAnswer;
use App\Models\LevelTestAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Saves a batch of month-end test answers.
 *
 * A 300-question test cannot be one request per answer: a student on hotel wifi in
 * Tashkent would lose half of them. So the client buffers and syncs in batches, which
 * makes two properties essential.
 *
 * IDEMPOTENT BY CONSTRUCTION. Every write is an upsert keyed on
 * (attempt, question) and every counter is RECOMPUTED, never incremented. Replaying
 * the same batch ten times therefore lands on exactly the same state — no
 * de-duplication table, no bookkeeping that can drift. `client_batch_uuid` is recorded
 * for audit and to tell the client its batch was already seen, not to enforce
 * correctness.
 *
 * INCAPABLE OF LEAKING THE ANSWER KEY. This path never reads
 * `questions.correct_option` and never writes `is_correct`, which stays NULL until
 * grading. There is no code here that could be coaxed into revealing a verdict
 * mid-test, so no amount of fiddling with the payload will get one out.
 */
final readonly class SyncLevelTestAnswersAction
{
    public function __construct(
        private SubmitLevelTestAction $submit,
    ) {}

    /**
     * @param  array<int, TestAnswerData>  $answers
     */
    public function __invoke(
        LevelTestAttempt $attempt,
        array $answers,
        ?string $clientBatchUuid = null,
    ): TestSyncResultData {
        $this->assertUsable($attempt);

        return DB::transaction(function () use ($attempt, $answers, $clientBatchUuid): TestSyncResultData {
            /** @var LevelTestAttempt $locked */
            $locked = LevelTestAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertUsable($locked);

            $replayed = $clientBatchUuid !== null && $locked->answers()
                ->where('client_batch_uuid', $clientBatchUuid)
                ->exists();

            // Only questions actually in this attempt. Otherwise a student could answer
            // another student's test, or pad their own with questions of their choosing.
            $permitted = array_flip($locked->question_order ?? []);
            $rejected = [];
            $rows = [];
            $now = now();

            foreach (array_slice($answers, 0, (int) config('quiz.test.batch_max')) as $answer) {
                if (! isset($permitted[$answer->questionId])) {
                    $rejected[] = $answer->questionId;

                    continue;
                }

                $rows[$answer->questionId] = [
                    'level_test_attempt_id' => $locked->getKey(),
                    'question_id' => $answer->questionId,
                    'selected_option' => $answer->selectedOption?->value,
                    'is_flagged' => $answer->isFlagged,
                    'time_spent_ms' => $answer->clampedTimeSpentMs(),
                    'client_batch_uuid' => $clientBatchUuid,
                    'answered_at' => $answer->answeredAt !== null
                        // Never trust a device clock for an audited timestamp.
                        ? min($answer->answeredAt->toDateTime(), $now->toDateTime())
                        : $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                // A duplicated question id inside one batch is collapsed above (keyed
                // array), because MySQL rejects an upsert that names the same key twice.
                LevelTestAnswer::query()->upsert(
                    array_values($rows),
                    ['level_test_attempt_id', 'question_id'],
                    ['selected_option', 'is_flagged', 'time_spent_ms', 'client_batch_uuid', 'answered_at', 'updated_at'],
                );
            }

            $progress = $this->recount($locked);

            $locked->forceFill([
                'answered_count' => $progress['answered'],
                'flagged_count' => $progress['flagged'],
                // Furthest point reached, so an out-of-order batch cannot rewind the
                // student's place in the paper.
                'current_position' => max($locked->current_position, $this->positionOf($locked, $rows)),
                'last_sync_at' => $now,
            ])->save();

            return new TestSyncResultData(
                accepted: count($rows),
                rejected: count($rejected),
                answeredCount: $progress['answered'],
                flaggedCount: $progress['flagged'],
                totalQuestions: $locked->total_questions,
                currentPosition: $locked->current_position,
                expiresAt: $locked->expires_at?->toIso8601String(),
                secondsRemaining: $this->secondsRemaining($locked),
                replayed: $replayed,
                rejectedQuestionIds: $rejected,
            );
        });
    }

    /**
     * The attempt must be open, and inside its deadline plus a small grace.
     *
     * The grace exists for the answer that was typed at 29:58 and left the phone at
     * 30:03. Beyond it the attempt is closed and graded immediately, so the student gets
     * a result rather than a rejected sync and a frozen screen.
     */
    private function assertUsable(LevelTestAttempt $attempt): void
    {
        if (! $attempt->isOpen()) {
            throw new TestAttemptClosedException(meta: ['status' => $attempt->status->value]);
        }

        if ($attempt->expires_at === null) {
            return;
        }

        $deadline = $attempt->expires_at->copy()->addSeconds((int) config('quiz.test.grace_seconds'));

        if ($deadline->isPast()) {
            ($this->submit)($attempt, dueToExpiry: true);

            throw new TestAttemptExpiredException(meta: [
                'expired_at' => $attempt->expires_at->toIso8601String(),
            ]);
        }
    }

    /** @return array{answered: int, flagged: int} */
    private function recount(LevelTestAttempt $attempt): array
    {
        /** @var object{answered: int|null, flagged: int|null} $row */
        $row = DB::selectOne(
            'select
                sum(case when selected_option is not null then 1 else 0 end) as answered,
                sum(case when is_flagged = 1 then 1 else 0 end) as flagged
             from level_test_answers
             where level_test_attempt_id = ?',
            [$attempt->getKey()],
        );

        return [
            'answered' => (int) ($row->answered ?? 0),
            'flagged' => (int) ($row->flagged ?? 0),
        ];
    }

    /**
     * Position of the furthest question in this batch, 1-indexed against question_order.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function positionOf(LevelTestAttempt $attempt, array $rows): int
    {
        if ($rows === []) {
            return $attempt->current_position;
        }

        $positions = array_flip($attempt->question_order ?? []);
        $best = $attempt->current_position;

        foreach (array_keys($rows) as $questionId) {
            $best = max($best, ($positions[$questionId] ?? 0) + 1);
        }

        return min($best, max(1, $attempt->total_questions));
    }

    private function secondsRemaining(LevelTestAttempt $attempt): int
    {
        if ($attempt->expires_at === null) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($attempt->expires_at, absolute: false));
    }
}
