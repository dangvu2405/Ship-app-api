<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('customer');
        $companyId = $this->input('company_id');

        return [
            'company_id' => 'sometimes|integer|exists:companies,id',
            'type' => 'sometimes|in:individual,company',
            'name' => 'sometimes|string|max:255',
            'tax_code' => ['nullable', 'string', 'max:50',
                Rule::unique('customers', 'tax_code')->where('company_id', $companyId)->ignore($id)],
            'phone' => 'nullable|string|max:20',
            'email' => ['nullable', 'email', 'max:255',
                Rule::unique('customers', 'email')->where('company_id', $companyId)->ignore($id)],
            'address' => 'nullable|string',
        ];
    }
}
