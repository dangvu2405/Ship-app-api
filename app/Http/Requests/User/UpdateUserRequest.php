<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('user');

        return [
            'username' => 'sometimes|string|max:255|unique:users,username,' . $id,
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'avatar_url' => 'nullable|url|max:255',
            'password' => ['sometimes', 'nullable', 'string', Password::min(6)],
            'driver_id' => 'nullable|exists:drivers,id',
            'status' => 'sometimes|in:active,inactive',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20|regex:/^0[0-9]{9,10}$/',
            'residential_address' => 'nullable|string|max:500',
        ];
    }
}
