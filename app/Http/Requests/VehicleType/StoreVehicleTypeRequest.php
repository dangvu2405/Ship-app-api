<?php

declare(strict_types=1);

namespace App\Http\Requests\VehicleType;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class StoreVehicleTypeRequest extends AppFormRequest
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
            'name' => ['required', 'string', 'max:100', Rule::unique('vehicle_types', 'name')->where(
                fn ($query) => $query->where('company_id', $this->input('company_id'))
            )],
            'max_load_ton' => ['nullable', 'numeric', 'min:0'],
            'volume_m3' => ['nullable', 'numeric', 'min:0'],
            'required_license_class' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
