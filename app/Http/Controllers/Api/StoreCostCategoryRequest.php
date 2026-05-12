<?php

declare(strict_types=1);

namespace App\Http\Requests\CostCategory;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('cost_categories', 'code')
                    ->where('company_id', app(TenantContext::class)->getCompanyId())
            ],
            'name' => ['required', 'string', 'max:100'],
            'requires_receipt' => ['nullable', 'boolean'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}