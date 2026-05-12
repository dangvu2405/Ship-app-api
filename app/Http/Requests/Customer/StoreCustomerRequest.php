<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('orders', 'create');
    }

    public function prepareForValidation(): void
    {
        $tenantId = $this->tenantCompanyId();
        if ($tenantId !== null && $tenantId > 0) {
            $this->merge(['company_id' => $tenantId]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantCompanyId();

        return [
            'company_id' => array_values(array_filter([
                'required',
                'integer',
                Rule::exists('companies', 'id'),
                $tenantId !== null ? Rule::in([$tenantId]) : null,
            ])),
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
