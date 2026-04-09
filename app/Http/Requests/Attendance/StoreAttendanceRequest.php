<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'date' => [
                'required',
                'date',
                Rule::unique('attendances', 'date')->where(fn (Builder $query): Builder => $query
                    ->where('employee_id', (int) $this->input('employee_id'))
                    ->whereNull('deleted_at')),
            ],
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'work_hours' => 'nullable|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'status' => 'required|in:present,absent,late,half_day,leave',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('check_in') && $this->filled('check_out')) {
            $checkIn = Carbon::createFromFormat('H:i', (string) $this->input('check_in'));
            $checkOut = Carbon::createFromFormat('H:i', (string) $this->input('check_out'));

            if ($checkOut->greaterThanOrEqualTo($checkIn)) {
                $minutes = $checkOut->diffInMinutes($checkIn);
                $workHours = round($minutes / 60, 2);
                $overtimeHours = round(max(0, $workHours - 8), 2);

                $merged = [];
                if (! $this->filled('work_hours')) {
                    $merged['work_hours'] = $workHours;
                }
                if (! $this->filled('overtime_hours')) {
                    $merged['overtime_hours'] = $overtimeHours;
                }

                if ($merged !== []) {
                    $this->merge($merged);
                }
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('check_in') xor $this->filled('check_out')) {
                $validator->errors()->add('check_out', 'check_in và check_out phải nhập cùng nhau.');
            }

            if ($this->filled('check_in') && $this->filled('check_out')) {
                $checkIn = Carbon::createFromFormat('H:i', (string) $this->input('check_in'));
                $checkOut = Carbon::createFromFormat('H:i', (string) $this->input('check_out'));

                if ($checkOut->lessThan($checkIn)) {
                    $validator->errors()->add('check_out', 'check_out phải sau hoặc bằng check_in.');
                }
            }

            $status = (string) $this->input('status');
            $workHours = (float) $this->input('work_hours', 0);
            if (in_array($status, ['absent', 'leave'], true) && $workHours > 0) {
                $validator->errors()->add('work_hours', 'work_hours phải bằng 0 khi trạng thái là absent hoặc leave.');
            }
        });
    }
}
