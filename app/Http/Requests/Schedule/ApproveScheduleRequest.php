<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hos_override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
