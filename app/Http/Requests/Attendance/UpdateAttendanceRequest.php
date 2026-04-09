<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attendanceId = (int) $this->route('attendance');
        $attendance = Attendance::query()->find($attendanceId);
        $effectiveEmployeeId = (int) $this->input('employee_id', $attendance?->employee_id);

        return [
            'employee_id' => [
                'sometimes',
                Rule::exists('employees', 'id'),
            ],
            'date' => [
                'sometimes',
                'date',
                Rule::unique('attendances', 'date')
                    ->ignore($attendanceId)
                    ->where(fn (Builder $query): Builder => $query
                        ->where('employee_id', $effectiveEmployeeId)
                        ->whereNull('deleted_at')),
            ],
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'work_hours' => 'nullable|numeric|min:0',
            'overtime_hours' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:present,absent,late,half_day,leave',
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
            $attendance = Attendance::query()->find((int) $this->route('attendance'));
            if (! $attendance) {
                return;
            }

            $checkIn = $this->input('check_in');
            $checkOut = $this->input('check_out');

            if (($this->has('check_in') || $this->has('check_out')) && (empty($checkIn) || empty($checkOut))) {
                $validator->errors()->add('check_out', 'check_in và check_out phải nhập cùng nhau.');
            }

            if ($this->filled('check_in') && $this->filled('check_out')) {
                $checkInTime = Carbon::createFromFormat('H:i', (string) $checkIn);
                $checkOutTime = Carbon::createFromFormat('H:i', (string) $checkOut);

                if ($checkOutTime->lessThan($checkInTime)) {
                    $validator->errors()->add('check_out', 'check_out phải sau hoặc bằng check_in.');
                }
            }

            $status = (string) $this->input('status', $attendance->status);
            $workHours = (float) $this->input('work_hours', $attendance->work_hours);
            if (in_array($status, ['absent', 'leave'], true) && $workHours > 0) {
                $validator->errors()->add('work_hours', 'work_hours phải bằng 0 khi trạng thái là absent hoặc leave.');
            }
        });
    }
}
