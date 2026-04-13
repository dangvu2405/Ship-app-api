<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'driver_id'  => ['required', 'integer', 'exists:drivers,id'],
            'office_id'  => ['required', 'integer', 'exists:offices,id'],
            'work_date'  => ['required', 'date', 'after_or_equal:today'],
            'shift_code' => ['sometimes', 'string', 'in:day,night,split,custom'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ];
    }
}
