<?php

declare(strict_types=1);

namespace App\Http\Requests\Overtime;

use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeRequest extends FormRequest
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
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'work_date'  => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'ot_hours'   => ['required', 'numeric', 'min:0.5', 'max:8'],
            'reason'     => ['nullable', 'string', 'max:500'],
        ];
    }
}
