<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class AdjustAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'check_in'       => ['sometimes', 'date_format:Y-m-d H:i:s'],
            'check_out'      => ['nullable', 'date_format:Y-m-d H:i:s', 'after:check_in'],
            'work_hours'     => ['sometimes', 'numeric', 'min:0', 'max:24'],
            'overtime_hours' => ['sometimes', 'numeric', 'min:0', 'max:8'],
            'status'         => ['sometimes', 'string', 'in:present,absent,late,half_day,leave'],
            'reason'         => ['required_with:check_in,check_out,status', 'string', 'max:500'],
        ];
    }
}
