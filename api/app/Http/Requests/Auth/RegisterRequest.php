<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            // No `dns` rule: it puts a network lookup on the registration path and
            // is flaky. Deliverability is proven properly by the verification email.
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            // Password::defaults() is configured in AppServiceProvider: strict with a
            // breach check in production, relaxed locally and in tests.
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'timezone' => ['sometimes', 'string', 'timezone:all', 'max:64'],
            'locale' => ['sometimes', 'string', 'in:en'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
