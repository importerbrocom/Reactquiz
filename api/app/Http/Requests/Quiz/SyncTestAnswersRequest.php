<?php

declare(strict_types=1);

namespace App\Http\Requests\Quiz;

use App\DTOs\Quiz\TestAnswerData;
use App\Enums\OptionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A batch of month-end test answers.
 *
 * The batch size is capped in validation as well as in the action: rejecting an
 * oversized request before it reaches the database is cheaper than trimming it after.
 */
final class SyncTestAnswersRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_batch_uuid' => ['required', 'uuid'],
            'answers' => ['required', 'array', 'min:1', 'max:'.config('quiz.test.batch_max')],
            'answers.*.question_id' => ['required', 'integer', 'min:1'],
            // Nullable on purpose: null clears a previous choice.
            'answers.*.selected_option' => ['nullable', Rule::enum(OptionKey::class)],
            'answers.*.is_flagged' => ['nullable', 'boolean'],
            'answers.*.time_spent_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'answers.*.answered_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'answers.max' => 'Too many answers in one batch. Send at most :max at a time.',
        ];
    }

    /** @return array<int, TestAnswerData> */
    public function toData(): array
    {
        return array_map(
            static fn (array $row): TestAnswerData => TestAnswerData::fromArray($row),
            $this->validated('answers'),
        );
    }
}
