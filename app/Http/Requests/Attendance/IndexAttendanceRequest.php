<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class IndexAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'date'      => ['nullable', 'date'],
            'from'      => ['nullable', 'date', 'required_with:to'],
            'to'        => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
            'status'    => ['nullable', 'string', 'max:20'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
