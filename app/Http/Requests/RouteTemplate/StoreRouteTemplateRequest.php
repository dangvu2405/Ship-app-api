<?php

declare(strict_types=1);

namespace App\Http\Requests\RouteTemplate;

use App\Http\Requests\AppFormRequest;

final class StoreRouteTemplateRequest extends AppFormRequest
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
            'origin_location_id' => ['required', 'integer', 'exists:locations,id', 'different:destination_location_id'],
            'destination_location_id' => ['required', 'integer', 'exists:locations,id'],
            'distance_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'estimated_hours' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'default_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fuel_norm_liter' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'toll_norm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
