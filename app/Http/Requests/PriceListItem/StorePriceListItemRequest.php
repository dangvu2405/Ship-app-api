<?php

declare(strict_types=1);

namespace App\Http\Requests\PriceListItem;

use App\Http\Requests\AppFormRequest;
use App\Models\PriceList;
use Illuminate\Validation\Rule;

final class StorePriceListItemRequest extends AppFormRequest
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
            'price_list_id' => [
                'required',
                'integer',
                Rule::exists('price_lists', 'id')->where(fn ($query) => $query->where('company_id', $this->input('company_id'))),
            ],
            'route_template_id' => ['nullable', 'integer', Rule::exists('route_templates', 'id')->where(fn ($query) => $query->where('company_id', $this->input('company_id')))],
            'vehicle_type_id' => ['nullable', 'integer', Rule::exists('vehicle_types', 'id')->where(fn ($query) => $query->where('company_id', $this->input('company_id')))],
            'cargo_type_id' => ['nullable', 'integer', Rule::exists('cargo_types', 'id')->where(fn ($query) => $query->where('company_id', $this->input('company_id')))],
            'price' => ['required', 'numeric', 'min:0'],
            'price_unit' => ['required', 'in:per_trip,per_km,per_ton'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
