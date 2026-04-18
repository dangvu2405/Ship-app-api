<?php

declare(strict_types=1);

namespace App\Http\Requests\PayrollAdjustment;

use Illuminate\Foundation\Http\FormRequest;

final class StorePayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payroll_id' => ['required', 'exists:payrolls,id'],
            'driver_id' => ['required', 'exists:drivers,id'],
            'type' => ['required', 'in:addition,deduction'],
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:1000'],
            'category' => ['nullable', 'in:violation_refund,leave_restore,ot_late_approval,manual'],
            'source_type' => ['nullable', 'string', 'max:50'],
            'source_id' => ['nullable', 'integer'],
        ];
    }
}
