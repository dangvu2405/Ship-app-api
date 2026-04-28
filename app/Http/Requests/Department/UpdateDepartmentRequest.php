<?php

declare(strict_types=1);

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('department');
        $officeId = $this->input('office_id');

        return [
            'office_id' => 'sometimes|exists:offices,id',
            'parent_id' => 'nullable|exists:departments,id',
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('departments', 'code')
                    ->where('office_id', $officeId)
                    ->ignore($id),
            ],
            'name' => 'sometimes|string|max:255',
        ];
    }
}
