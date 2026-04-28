<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\AppFormRequest;

class CheckOtpRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('api.validation.required'),
            'email.email' => __('api.validation.email'),
            'email.max' => __('api.validation.max.string'),
            'otp.required' => __('api.validation.required'),
            'otp.digits' => __('api.validation.digits'),
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => __('api.attributes.email'),
            'otp' => __('api.attributes.otp'),
        ];
    }
}
