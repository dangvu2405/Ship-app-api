<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

final class IndexScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'driver_id' => ['sometimes', 'integer', 'exists:drivers,id'],
            'office_id' => ['sometimes', 'integer', 'exists:offices,id'],
            'work_date' => ['sometimes', 'date'],
            'from' => ['sometimes', 'date', 'required_with:to'],
            'to' => ['sometimes', 'date', 'required_with:from', 'after_or_equal:from'],
            'status' => ['sometimes', 'string', 'in:draft,submitted,approved,locked'],
        ];
    }
}
