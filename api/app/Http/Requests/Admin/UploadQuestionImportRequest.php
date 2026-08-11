<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the question import file upload.
 * Accepts .xlsx and .csv files up to the configured max size.
 */
class UploadQuestionImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Role enforced by middleware
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxMb = config('quiz.import.max_upload_mb', 10);

        return [
            'file' => [
                'required',
                'file',
                "max:{$maxMb}000", // KB
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,application/csv',
            ],
            'level_id' => ['required', 'integer', 'exists:levels,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.mimetypes' => 'Only .xlsx and .csv files are accepted.',
            'level_id.required' => 'A target level is required for the import.',
            'level_id.exists' => 'The selected level does not exist.',
        ];
    }
}
