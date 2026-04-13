<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'avatar_url' => 'nullable|url|max:255',
            'password' => ['required', 'string', Password::min(6)],
            'driver_id' => 'nullable|exists:drivers,id',
            'status' => 'required|in:active,inactive',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20|regex:/^0[0-9]{9,10}$/',
            'residential_address' => 'nullable|string|max:500',
        ];
    }
}
