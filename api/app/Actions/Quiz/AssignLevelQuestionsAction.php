<?php

declare(strict_types=1);

namespace App\Actions\Quiz;

use App\Enums\ContentStatus;
use App\Enums\SelectionMode;
use App\Exceptions\InsufficientQuestionPoolException;
use App\Models\EnrollmentDayQuestion;
use App\Models\LevelEnrollment;
use App\Support\SeededShuffler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deals a level's questions into one student's days (docs/adr/001).
 *
 * Every student receives an *exhaustive personal permutation*: all 300 questions,
 * grouped differently. That reconciles two requirements which normally conflict —
 * "everyone must complete all of them" and "no two students get the same Day 1" — by
 * shuffling the grouping rather than the selection.
 *
 * Runs once per level enrolment, as a single bulk insert. A repeat cycle is simply a
 * new enrolment row with a new seed, which is why cycles cost almost no extra code.
 */
final readonly class AssignLevelQuestionsAction
{
    public function __invoke(LevelEnrollment $enrolment): int
    {
        if ($enrolment->questionsAreAssigned()) {
            return $enrolment->dayQuestions()->count();
        }

        $level = $enrolment->level;
        $required = $level->requiredQuestionCount();

        // Two narrow columns only: the pool can be thousands of rows.
        $pool = $level->questions()
            ->where('status', ContentStatus::Active->value)
            ->get(['id', 'topic']);

        if ($pool->count() < $required) {
            throw new InsufficientQuestionPoolException(
                "This level has {$pool->count()} active questions but needs {$required}.",
                ['available' => $pool->count(), 'required' => $required],
            );
        }

        // fixed_shared mode is the same algorithm with the level id as the seed, so
        // every student is dealt an identical hand. One code path, two behaviours.
        $seed = $level->selection_mode === SelectionMode::FixedShared
            ? $level->getKey()
            : $enrolment->assignment_seed;

        $ordered = SeededShuffler::shuffle($pool->all(), $seed);

        if ($level->spread_topics_across_days) {
            $ordered = $this->spreadTopics($ordered, $level->daily_question_count, $seed);
        }

        $ordered = array_slice($ordered, 0, $required);

        return DB::transaction(function () use ($enrolment, $level, $ordered): int {
            $rows = [];
            $now = now();

            foreach (array_chunk($ordered, $level->daily_question_count) as $dayIndex => $questions) {
                foreach ($questions as $position => $question) {
                    $rows[] = [
                        'level_enrollment_id' => $enrolment->getKey(),
                        'user_id' => $enrolment->user_id,
                        'level_id' => $level->getKey(),
                        'day_number' => $dayIndex + 1,
                        'question_id' => $question->id,
                        'position' => $position + 1,
                        'assigned_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // One insert per chunk, never one per question.
            foreach (array_chunk($rows, 500) as $chunk) {
                EnrollmentDayQuestion::query()->insert($chunk);
            }

            $enrolment->forceFill([
                'questions_assigned_at' => $now,
                'started_at' => $enrolment->started_at ?? $now,
            ])->save();

            return count($rows);
        });
    }

    /**
     * Redistribute an already-complete permutation so no day becomes a
     * single-subject drill.
     *
     * With authored difficulty removed, subject variety is the only quality control
     * left. A plain shuffle will occasionally hand a student a Day 1 of nine Anatomy
     * questions, which feels like a drill rather than exam practice.
     *
     * Deals by DAY rather than filling days in sequence. An earlier version walked
     * the days in order and pushed whatever would not fit into the next one, which
     * quietly piled every leftover into the final days — precisely the clustering it
     * was meant to prevent. Placing each question into the day where its subject is
     * currently least represented spreads an over-represented subject evenly by
     * construction: 18 Anatomy questions over 6 days become 3 per day, not 2-2-2-2-4-6.
     *
     * This only reorders — it never changes *which* questions a student receives, so
     * the "complete all of them" guarantee is untouched.
     *
     * @param  array<int, object>  $ordered
     * @return array<int, object>
     */
    private function spreadTopics(array $ordered, int $perDay, int $seed): array
    {
        $total = count($ordered);
        $dayCount = (int) ceil($total / $perDay);

        if ($dayCount <= 1) {
            return $ordered;
        }

        /** @var Collection<string, Collection<int, object>> $buckets */
        $buckets = collect($ordered)->groupBy(
            fn (object $question): string => (string) ($question->topic ?: 'unclassified'),
        );

        // Largest subject first: it has the least freedom, so it should choose slots
        // before the small buckets fill them.
        $queues = $buckets
            ->sortByDesc(fn (Collection $items): int => $items->count())
            ->map(fn (Collection $items): array => $items->values()->all());

        /** @var array<int, array<int, object>> $days */
        $days = array_fill(0, $dayCount, []);
        $topicCounts = array_fill(0, $dayCount, []);

        foreach ($queues as $topic => $items) {
            foreach ($items as $question) {
                $best = null;
                $bestKey = null;

                for ($day = 0; $day < $dayCount; $day++) {
                    if (count($days[$day]) >= $perDay) {
                        continue;                       // day is full
                    }

                    // Fewest of THIS subject first, then the emptiest day. Ties break
                    // on day order, which keeps the result deterministic.
                    $key = [$topicCounts[$day][$topic] ?? 0, count($days[$day]), $day];

                    if ($bestKey === null || $key < $bestKey) {
                        $bestKey = $key;
                        $best = $day;
                    }
                }

                if ($best === null) {
                    break 2;                            // every day full; nothing left to place
                }

                $days[$best][] = $question;
                $topicCounts[$best][$topic] = ($topicCounts[$best][$topic] ?? 0) + 1;
            }
        }

        // Shuffle within each day so position 1 is not always the largest subject.
        $result = [];
        foreach ($days as $day => $questions) {
            foreach (SeededShuffler::shuffle($questions, $seed + $day) as $question) {
                $result[] = $question;
            }
        }

        return $result;
    }
}
