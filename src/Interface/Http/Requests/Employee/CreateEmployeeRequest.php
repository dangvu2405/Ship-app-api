<?php

declare(strict_types=1);

namespace Src\Interface\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateEmployeeRequest extends FormRequest
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
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('employees', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^0[0-9]{9,10}$/'],
            'dob' => ['nullable', 'date', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'address' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(['office', 'driver'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'resigned'])],
            'join_date' => ['required', 'date', 'date_format:Y-m-d'],
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Employee code is required',
            'code.unique' => 'Employee code already exists',
            'code.max' => 'Employee code cannot exceed 50 characters',
            'name.required' => 'Employee name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Email must be a valid email address',
            'email.unique' => 'Email already registered',
            'phone.regex' => 'Phone number must start with 0 and contain 10-11 digits',
            'type.required' => 'Employee type is required',
            'type.in' => 'Employee type must be either office or driver',
            'join_date.required' => 'Join date is required',
            'join_date.date_format' => 'Join date must be in Y-m-d format',
            'office_id.required' => 'Office is required',
            'office_id.exists' => 'Selected office does not exist',
            'position_id.required' => 'Position is required',
            'position_id.exists' => 'Selected position does not exist',
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
}
