<?php

declare(strict_types=1);

namespace App\Http\Requests\Workforce;

use Illuminate\Foundation\Http\FormRequest;

final class WorkforceLeaveRequestsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'status' => ['nullable', 'in:pending,approved,rejected,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
