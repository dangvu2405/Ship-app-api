<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manager override: same fields as partial update plus mandatory override_reason.
 */
final class OverrideScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'work_date' => ['sometimes', 'date'],
            'shift_code' => ['sometimes', 'string', 'in:day,night,split,custom'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'override_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
