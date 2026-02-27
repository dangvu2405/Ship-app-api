<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'office_id' => 'required|exists:offices,id',
            'parent_id' => 'nullable|exists:departments,id',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
        ];
    }
}
