<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends AppFormRequest
{
    public function rules(): array
    {
        $emailFormat = app()->environment('testing') ? 'email:rfc' : 'email:rfc,dns';

        return [
            'username' => ['required', 'string', 'max:100', 'unique:users,username', 'regex:/^[a-zA-Z0-9_]+$/'],
            'email' => ['required', 'string', $emailFormat, 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->uncompromised()],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => __('api.validation.required'),
            'username.max' => __('api.validation.max.string'),
            'username.unique' => __('api.validation.unique'),
            'username.regex' => __('api.validation.username_regex'),
            'email.required' => __('api.validation.required'),
            'email.email' => __('api.validation.email'),
            'email.max' => __('api.validation.max.string'),
            'email.unique' => __('api.validation.unique'),
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
            'username' => __('api.attributes.username'),
            'email' => __('api.attributes.email'),
            'password' => __('api.attributes.password'),
        ];
    }
}
