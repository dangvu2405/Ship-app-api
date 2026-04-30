<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->filled('company_id')) {
            return;
        }

        $tenant_id = app(TenantContext::class)->getCompanyId();
        if ($tenant_id !== null && $tenant_id > 0) {
            $this->merge(['company_id' => $tenant_id]);

            return;
        }

    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'type' => 'required|in:individual,company',
            'name' => 'required|string|max:255',
            'tax_code' => ['nullable', 'string', 'max:50',
                Rule::unique('customers', 'tax_code')->where('company_id', $this->input('company_id'))],
            'phone' => 'nullable|string|max:20',
            'email' => ['nullable', 'email', 'max:255',
                Rule::unique('customers', 'email')->where('company_id', $this->input('company_id'))],
            'address' => 'nullable|string',
        ];
    }
}
