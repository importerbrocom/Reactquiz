<?php

declare(strict_types=1);

namespace App\Http\Requests\Quiz;

use App\DTOs\Quiz\AnswerSubmissionData;
use App\Enums\OptionKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One answer from a student.
 *
 * Note the fields that are NOT accepted: is_correct, score, mastered. They are absent
 * from the rules and absent from AnswerSubmissionData, so a client that sends them is
 * not rejected — it is simply ignored, which is the more robust outcome. Correctness
 * comes from the database, never from the request.
 */
final class SubmitAnswerRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer', 'min:1'],
            'selected_option' => ['required', Rule::enum(OptionKey::class)],

            // The idempotency key for the offline outbox. Client-generated, so a
            // replayed answer is recognised as the same answer.
            'client_answer_uuid' => ['required', 'uuid'],

            'time_spent_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'answered_at' => ['nullable', 'date'],
            'was_offline' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'client_answer_uuid.required' => 'A client answer id is required so retries are not double-counted.',
            'selected_option.enum' => 'Choose one of the options offered for this question.',
        ];
    }

    public function toData(): AnswerSubmissionData
    {
        return AnswerSubmissionData::fromArray($this->validated());
    }
}
