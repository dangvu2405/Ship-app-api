<?php

declare(strict_types=1);

namespace App\Http\Requests\PayrollAdjustment;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:addition,deduction'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'reason' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}
