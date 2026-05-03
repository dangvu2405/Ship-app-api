<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\AppFormRequest;

class ForgotPasswordRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('api.validation.required'),
            'email.email' => __('api.validation.email'),
            'email.max' => __('api.validation.max.string'),
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => __('api.attributes.email'),
        ];
    }
}
