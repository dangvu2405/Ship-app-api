<?php

declare(strict_types=1);

namespace App\Http\Requests\Deduction;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:deductions,code',
            'name' => 'required|string|max:255',
        ];
    }
}
