<?php

declare(strict_types=1);

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class ApproveOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:500'],
        ];
    }
}
