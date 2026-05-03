<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
            'waive_reason' => ['required_if:action,cancel', 'nullable', 'string', 'max:1000'],
        ];
    }
}
