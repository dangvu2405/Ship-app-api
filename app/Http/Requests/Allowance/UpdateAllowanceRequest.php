<?php

namespace App\Http\Requests\Allowance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAllowanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('allowance');

        return [
            'code' => 'sometimes|string|max:50|unique:allowances,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'default_amount' => 'nullable|numeric|min:0',
            'taxable' => 'nullable|boolean',
        ];
    }
}
