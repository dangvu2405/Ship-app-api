<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'avatar_url' => 'nullable|url|max:255',
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'status' => 'required|in:active,inactive',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20|regex:/^0[0-9]{9,10}$/',
            'residential_address' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => __('api.validation.required'),
            'username.max' => __('api.validation.max.string'),
            'username.unique' => __('api.validation.unique'),
            'email.required' => __('api.validation.required'),
            'email.email' => __('api.validation.email'),
            'email.max' => __('api.validation.max.string'),
            'email.unique' => __('api.validation.unique'),
            'password.required' => __('api.validation.required'),
            'password.min' => __('api.validation.min.string'),
            'password.mixed' => __('api.validation.password_mixed'),
            'password.numbers' => __('api.validation.password_numbers'),
            'status.required' => __('api.validation.required'),
            'status.in' => __('api.validation.in'),
            'emergency_contact_phone.regex' => __('api.validation.phone_vn'),
            'emergency_contact_phone.max' => __('api.validation.max.string'),
            'residential_address.max' => __('api.validation.max.string'),
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => __('api.attributes.username'),
            'email' => __('api.attributes.email'),
            'password' => __('api.attributes.password'),
            'avatar_url' => __('api.attributes.user_avatar_url'),
            'status' => __('api.attributes.user_status'),
            'emergency_contact_name' => __('api.attributes.user_emergency_contact_name'),
            'emergency_contact_phone' => __('api.attributes.user_emergency_contact_phone'),
            'residential_address' => __('api.attributes.user_residential_address'),
        ];
    }
}
