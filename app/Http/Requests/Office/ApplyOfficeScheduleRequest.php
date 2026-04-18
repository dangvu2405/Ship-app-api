<?php

declare(strict_types=1);

namespace App\Http\Requests\Office;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApplyOfficeScheduleRequest extends FormRequest
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
            'schedule_id' => ['required', 'integer', 'exists:work_schedule_templates,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'replace_drafts' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = $this->input('start_date');
            $end = $this->input('end_date');
            if (! is_string($start) || ! is_string($end)) {
                return;
            }
            $maxDays = (int) config('ship.office_schedule_apply.max_date_range_days', 120);
            $days = \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1;
            if ($days > $maxDays) {
                $validator->errors()->add('end_date', "Khoảng áp dụng không được vượt quá {$maxDays} ngày.");
            }
        });
    }
}
