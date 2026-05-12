<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('orders', 'edit');
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
        $id = $this->route('customer');
        $companyId = $this->input('company_id');
        $tenantId = $this->tenantCompanyId();

        return [
            'company_id' => array_values(array_filter([
                'sometimes',
                'integer',
                Rule::exists('companies', 'id'),
                $tenantId !== null ? Rule::in([$tenantId]) : null,
            ])),
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
