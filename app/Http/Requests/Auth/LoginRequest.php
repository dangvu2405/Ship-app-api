<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\AppFormRequest;

class LoginRequest extends AppFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email', ''))),
            'password' => (string) $this->input('password', ''),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('api.validation.required'),
            'email.email' => __('api.validation.email'),
            'email.max' => __('api.validation.max.string'),
            'password.required' => __('api.validation.required'),
            'password.max' => __('api.validation.max.string'),
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => __('api.attributes.email'),
            'password' => __('api.attributes.password'),
        ];
    }
}
