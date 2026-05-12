<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'otp_token' => ['required', 'string', 'uuid'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->uncompromised()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('api.validation.required'),
            'email.email' => __('api.validation.email'),
            'otp_token.required' => __('api.validation.required'),
            'otp_token.uuid' => __('api.validation.uuid'),
            'password.required' => __('api.validation.required'),
            'password.confirmed' => __('api.validation.confirmed'),
            'password.min' => __('api.validation.min.string'),
            'password.mixed' => __('api.validation.password_mixed'),
            'password.numbers' => __('api.validation.password_numbers'),
            'password.uncompromised' => __('api.validation.password_uncompromised'),
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => __('api.attributes.email'),
            'otp_token' => __('api.attributes.otp'),
            'password' => __('api.attributes.new_password'),
        ];
    }
}
