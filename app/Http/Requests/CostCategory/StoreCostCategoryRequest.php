<?php

declare(strict_types=1);

namespace App\Http\Requests\CostCategory;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class StoreCostCategoryRequest extends AppFormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique('cost_categories', 'code')->where(
                fn ($query) => $query->where('company_id', $this->input('company_id'))
            )],
            'name' => ['required', 'string', 'max:100'],
            'requires_receipt' => ['sometimes', 'boolean'],
            'approval_threshold' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
