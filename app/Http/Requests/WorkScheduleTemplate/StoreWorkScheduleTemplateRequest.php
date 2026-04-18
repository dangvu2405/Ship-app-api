<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkScheduleTemplate;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkScheduleTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole('admin');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:120'],
            'shift_code' => ['required', 'string', 'in:day,night,split,custom'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
