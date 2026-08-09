<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Enums\QuestionState;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptQuestion;
use Illuminate\Database\Eloquent\Collection;

/**
 * Serves one day's ten questions.
 *
 * Ten questions is small enough to send in one response, which is what lets the PWA
 * cache the whole day and work through a tunnel or a dropped connection. Two properties
 * make that safe:
 *
 *  - The payload contains no correct answers and no explanations, so caching it on the
 *    device gives nothing away (QuestionDeliveryService).
 *  - Option order is derived from each question's own `submission_count`, so it is
 *    identical when the cached copy is re-read and only moves once the student has
 *    actually answered (docs/adr/003).
 */
final class DailyQuizDeliveryService
{
    public function __construct(
        private readonly QuestionDeliveryService $delivery,
        private readonly OptionOrderService $orders,
    ) {}

    /** @return array<string, mixed> */
    public function payload(QuizAttempt $attempt): array
    {
        /** @var Collection<int, QuizAttemptQuestion> $states */
        $states = $attempt->questions()->orderBy('position')->get();
        $questions = $this->delivery->load($states->pluck('question_id')->all());

        $scope = $this->orders->dailyScope(
            $attempt->level_enrollment_id,
            $attempt->day_number,
            $attempt->attempt_number,
        );

        $items = [];

        foreach ($states as $state) {
            /** @var Question|null $question */
            $question = $questions->get($state->question_id);

            if ($question === null) {
                continue;
            }

            $items[] = $this->delivery->present(
                question: $question,
                position: $state->position,
                scope: $scope,
                // The exposure counter. Stable across refreshes, advances on answer.
                submissionCount: $state->submission_count,
                selectedOption: $state->selected_option?->value,
                state: $state->state->value,
            );
        }

        return [
            'attempt_uuid' => $attempt->uuid,
            'day_number' => $attempt->day_number,
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status->value,
            'required_count' => $attempt->required_count,
            'mastered_count' => $attempt->mastered_count,
            'retry_mode' => $attempt->level->retry_mode->value,
            'current_position' => $attempt->current_position,
            // Where to resume: the first question still needing work, so a student
            // returning mid-day is not made to scroll past what they have finished.
            'resume_question_id' => $states
                ->first(fn (QuizAttemptQuestion $s): bool => $s->state !== QuestionState::Mastered)
                ?->question_id,
            'time_spent_seconds' => $attempt->time_spent_seconds,
            'questions' => $items,
        ];
    }
}
