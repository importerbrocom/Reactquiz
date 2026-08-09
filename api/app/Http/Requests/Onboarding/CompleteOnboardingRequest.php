<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The choices a student makes when they first open the app.
 *
 * The programme is validated as belonging to the chosen exam category, so a client
 * cannot pair "FMGE" with an AMC programme and end up enrolled in the wrong syllabus.
 */
final class CompleteOnboardingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'exam_category_id' => [
                'required', 'integer',
                Rule::exists('exam_categories', 'id')->where('status', 'active'),
            ],
            'programme_id' => [
                'required', 'integer',
                Rule::exists('programmes', 'id')
                    ->where('status', 'active')
                    ->where('exam_category_id', $this->integer('exam_category_id')),
            ],
            'timezone' => ['nullable', 'timezone'],
            'language' => ['nullable', 'string', 'size:2'],
            'reminder_time' => ['nullable', 'date_format:H:i'],
            'notifications_opt_in' => ['nullable', 'boolean'],
            'study_goal' => ['nullable', 'string', 'max:32'],
            'daily_goal_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'programme_id.exists' => 'That programme is not available for the exam you chose.',
        ];
    }
}
