<?php

declare(strict_types=1);

namespace App\Http\Requests\CostCategory;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class UpdateCostCategoryRequest extends AppFormRequest
{
    public function rules(): array
    {
        $costCategoryId = (int) $this->route('cost_category');

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('cost_categories', 'code')
                ->ignore($costCategoryId)
                ->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId()))],
            'name' => ['sometimes', 'string', 'max:100'],
            'requires_receipt' => ['sometimes', 'boolean'],
            'approval_threshold' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
