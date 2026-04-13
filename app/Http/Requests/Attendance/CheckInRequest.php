<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'driver_id'    => ['required', 'integer', 'exists:drivers,id'],
            'check_in_time' => ['required', 'date_format:Y-m-d H:i:s'],
        ];
    }
}
