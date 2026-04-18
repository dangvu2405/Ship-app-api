<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkScheduleTemplate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkScheduleTemplateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'shift_code' => ['sometimes', 'string', 'in:day,night,split,custom'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
