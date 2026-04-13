<?php

declare(strict_types=1);

namespace App\Http\Requests\Office;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|exists:companies,id',
            'code' => 'sometimes|string|max:50',
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'manager_id' => 'nullable|exists:drivers,id',
        ];
    }
}
