<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\StudentQuestionProgress;
use App\Services\Student\StudentContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The questions this student gets wrong — with the answers, on purpose.
 *
 * This is the one student-facing endpoint that DOES reveal correct answers and
 * explanations, and it is safe precisely because of what it lists: questions already
 * answered wrong, which the student has therefore already been shown the answer to at
 * the moment they got it wrong. Nothing here is new information.
 *
 * The guard that matters is `wrong_count > 0`. Without it this endpoint would be a
 * complete answer key for the whole question bank.
 */
final class MistakeController extends Controller
{
    public function __construct(
        private readonly StudentContext $context,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'topic' => ['nullable', 'string', 'max:120'],
            'only_unmastered' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $enrolment = $this->context->currentLevelEnrolment($user);

        $progress = StudentQuestionProgress::query()
            ->where('user_id', $user->getKey())
            ->where('level_id', $enrolment->level_id)
            // THE guard. Only questions the student has actually got wrong.
            ->where('wrong_count', '>', 0)
            ->when(
                $validated['only_unmastered'] ?? false,
                fn ($query) => $query->where('is_mastered', false),
            )
            ->when(
                $validated['topic'] ?? null,
                fn ($query, string $topic) => $query->whereHas(
                    'question',
                    fn ($q) => $q->where('topic', $topic),
                ),
            )
            ->with(['question' => fn ($query) => $query->with('options')])
            ->orderByDesc('wrong_count')
            ->orderByDesc('last_attempted_at')
            ->cursorPaginate((int) ($validated['per_page'] ?? 20));

        $progress->setCollection(
            $progress->getCollection()->map($this->present(...))->filter()->values(),
        );

        return ApiResponse::cursorPaginated($progress, 'Your mistakes.');
    }

    /** @return array<string, mixed>|null */
    private function present(StudentQuestionProgress $progress): ?array
    {
        /** @var Question|null $question */
        $question = $progress->question;

        if ($question === null) {
            return null;   // question deleted by an admin since
        }

        return [
            'question_id' => $question->getKey(),
            'question_text' => $question->question_text,
            'topic' => $question->topic,
            'options' => $question->options
                ->sortBy('display_order')
                ->map(fn ($option): array => [
                    'key' => $option->option_key->value,
                    'text' => $option->option_text,
                    'is_correct' => $option->is_correct,
                ])
                ->values()
                ->all(),
            'correct_option' => $question->correct_option->value,
            'explanation' => $question->explanation,
            'your_history' => [
                'attempts' => $progress->attempts,
                'wrong_count' => $progress->wrong_count,
                'correct_count' => $progress->correct_count,
                'last_selected_option' => $progress->last_selected_option?->value,
                'is_mastered' => $progress->is_mastered,
                'first_attempt_correct' => $progress->first_attempt_correct,
                'last_attempted_at' => $progress->last_attempted_at?->toIso8601String(),
            ],
        ];
    }
}
