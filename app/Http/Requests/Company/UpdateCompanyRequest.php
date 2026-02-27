<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('company');

        return [
            'code' => 'sometimes|string|max:50|unique:companies,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'tax_code' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'status' => 'sometimes|in:active,inactive',
        ];
    }
}
