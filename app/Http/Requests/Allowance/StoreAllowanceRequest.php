<?php

namespace App\Http\Requests\Allowance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAllowanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:allowances,code',
            'name' => 'required|string|max:255',
            'default_amount' => 'nullable|numeric|min:0',
            'taxable' => 'nullable|boolean',
        ];
    }
}
