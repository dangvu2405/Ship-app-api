<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('employee');

        return [
            'code' => 'sometimes|string|max:50|unique:employees,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|unique:employees,email,' . $id,
            'phone' => 'nullable|string|max:20|regex:/^0[0-9]{9,10}$/',
            'dob' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string',
            'avatar_url' => 'nullable|url|max:255',
            'national_id_no' => 'nullable|string|max:30',
            'national_id_issue_date' => 'nullable|date',
            'national_id_issue_place' => 'nullable|string|max:255',
            'social_insurance_no' => 'nullable|string|max:30',
            'health_insurance_no' => 'nullable|string|max:30',
            'insurance_registered_at' => 'nullable|date',
            'office_id' => 'sometimes|exists:offices,id',
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'sometimes|exists:positions,id',
            'type' => 'sometimes|in:office,driver',
            'status' => 'sometimes|in:active,inactive,resigned',
            'join_date' => 'sometimes|date',
            'resign_date' => 'nullable|date|after_or_equal:join_date',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
        ];
    }
}
