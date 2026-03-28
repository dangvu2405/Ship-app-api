<?php

declare(strict_types=1);

namespace App\Http\Requests\Deduction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('deduction');

        return [
            'code' => 'sometimes|string|max:50|unique:deductions,code,' . $id,
            'name' => 'sometimes|string|max:255',
        ];
    }
}
