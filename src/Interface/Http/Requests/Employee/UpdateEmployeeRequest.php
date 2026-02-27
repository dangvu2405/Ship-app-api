<?php

declare(strict_types=1);

namespace Src\Interface\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Application\Employee\DTOs\UpdateEmployeeData;

final class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $employeeId = $this->route('employee');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('employees', 'code')->ignore($employeeId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->ignore($employeeId),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^0[0-9]{9,10}$/'],
            'dob' => ['nullable', 'date', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'address' => ['nullable', 'string', 'max:500'],
            'type' => ['sometimes', Rule::in(['office', 'driver'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'resigned'])],
            'join_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'resign_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:join_date'],
            'office_id' => ['sometimes', 'integer', Rule::exists('offices', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'position_id' => ['sometimes', 'integer', Rule::exists('positions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Employee code already exists',
            'email.unique' => 'Email already registered',
            'phone.regex' => 'Phone number must start with 0 and contain 10-11 digits',
            'resign_date.after_or_equal' => 'Resign date must be after or equal to join date',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && $this->email !== null) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }

        if ($this->has('code') && $this->code !== null) {
            $this->merge([
                'code' => strtoupper(trim($this->code)),
            ]);
        }
    }

    public function toUpdateData(): UpdateEmployeeData
    {
        return UpdateEmployeeData::fromArray($this->validated());
    }
}
