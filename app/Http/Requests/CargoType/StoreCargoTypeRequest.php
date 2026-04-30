<?php

declare(strict_types=1);

namespace App\Http\Requests\CargoType;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class StoreCargoTypeRequest extends AppFormRequest
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
            'name' => ['required', 'string', 'max:100', Rule::unique('cargo_types', 'name')->where(
                fn ($query) => $query->where('company_id', $this->input('company_id'))
            )],
            'requires_special_vehicle' => ['sometimes', 'boolean'],
            'special_requirements' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
