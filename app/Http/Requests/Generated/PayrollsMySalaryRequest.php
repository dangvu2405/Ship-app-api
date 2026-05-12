<?php

declare(strict_types=1);

namespace App\Http\Requests\Generated;

use Illuminate\Foundation\Http\FormRequest;

class PayrollsMySalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // TODO: add validation rules
        ];
    }
}
