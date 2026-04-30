<?php

declare(strict_types=1);

namespace App\Http\Requests\PriceList;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class StorePriceListRequest extends AppFormRequest
{
    public function prepareForValidation(): void
    {
        if ($this->filled('company_id')) {
            return;
        }

        $companyId = $this->tenantCompanyId();
        if ($companyId !== null) {
            $this->merge(['company_id' => $companyId]);
        }
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:200'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('company_id', $this->input('company_id'))),
            ],
        ];
    }
}
