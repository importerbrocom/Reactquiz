<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Deliberately no `exists` rule: the endpoint must not reveal whether an
        // account exists. The controller always returns 200.
        return [
            'email' => ['required', 'string', 'email', 'max:190'],
        ];
    }
}
