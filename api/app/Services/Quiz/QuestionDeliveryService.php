<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Builds the answer-free question payload that goes to the student.
 *
 * There is exactly ONE method in the codebase that turns a Question into something the
 * client sees, and this is it. That is the point: the columns that give the game away —
 * `correct_option`, `correct_answer_text`, `explanation`, and `question_options.is_correct`
 * — are not merely omitted from a resource, they are never SELECTed. A future
 * `->append()`, an accidental `$question->toArray()`, or a debug dump of this payload
 * cannot leak what was never loaded.
 *
 * Answers are revealed in one other place only, after a submission has been graded
 * (SubmitAnswerAction), and never during a month-end test.
 */
final class QuestionDeliveryService
{
    /** Columns safe to send to a student mid-quiz. */
    private const SAFE_COLUMNS = [
        'id', 'level_id', 'question_text',
        'question_image_path', 'question_image_width', 'question_image_height',
        'question_image_alt', 'topic', 'shuffle_options',
    ];

    public function __construct(
        private readonly OptionOrderService $orders,
    ) {}

    /**
     * Load questions by id with only the safe columns and their options.
     *
     * Returned keyed by id so callers can walk their own order — `whereIn` does not
     * preserve the order of the ids you hand it, and a test paper that silently
     * reordered itself between the window and the answer sheet would be a disaster.
     *
     * @param  array<int, int>  $questionIds
     * @return EloquentCollection<int, Question>
     */
    public function load(array $questionIds): EloquentCollection
    {
        if ($questionIds === []) {
            /** @var EloquentCollection<int, Question> $empty */
            $empty = new EloquentCollection;

            return $empty;
        }

        return Question::query()
            ->whereIn('id', $questionIds)
            ->with(['options' => fn ($query) => $query
                // is_correct is excluded here, not filtered later.
                ->select(['id', 'question_id', 'option_key', 'option_text',
                    'option_image_path', 'option_image_alt', 'display_order', 'pin_last'])
                ->orderBy('display_order'),
            ])
            ->get(self::SAFE_COLUMNS)
            ->keyBy('id');
    }

    /**
     * One question as the client should receive it.
     *
     * @param  int|string  $scope  exposure scope for option ordering (see OptionOrderService)
     * @param  int  $submissionCount  how many times this student has answered this
     *                                question in this attempt; holds the option order
     *                                steady until they answer again
     * @return array<string, mixed>
     */
    public function present(
        Question $question,
        int $position,
        int|string $scope,
        int $submissionCount = 0,
        ?string $selectedOption = null,
        bool $isFlagged = false,
        ?string $state = null,
    ): array {
        $order = $this->orders->for($question, $scope, $submissionCount);

        /** @var Collection<string, QuestionOption> $byKey */
        $byKey = $question->options->keyBy(fn (QuestionOption $o): string => $o->option_key->value);

        return [
            'position' => $position,
            'question_id' => $question->getKey(),
            'question_text' => $question->question_text,
            'image' => $question->question_image_path === null ? null : [
                'path' => $question->question_image_path,
                'width' => $question->question_image_width,
                'height' => $question->question_image_height,
                'alt' => $question->question_image_alt,
            ],
            'topic' => $question->topic,
            'options' => collect($order)
                ->map(fn (string $key, int $index): ?array => $this->presentOption($byKey->get($key), $index))
                ->filter()
                ->values()
                ->all(),
            // Echoed back so a resumed attempt shows the student's own choice, described
            // by canonical key — never by the position it happened to occupy.
            'selected_option' => $selectedOption,
            'is_flagged' => $isFlagged,
            'state' => $state,
        ];
    }

    /** @return array<string, mixed>|null */
    private function presentOption(?QuestionOption $option, int $index): ?array
    {
        if ($option === null) {
            return null;
        }

        return [
            // The stable identity the client sends back. Display position is
            // deliberately separate, so nothing downstream can confuse the two.
            'key' => $option->option_key->value,
            'display_position' => $index + 1,
            'text' => $option->option_text,
            'image' => $option->option_image_path === null ? null : [
                'path' => $option->option_image_path,
                'alt' => $option->option_image_alt,
            ],
        ];
    }
}
