<?php

declare(strict_types=1);

namespace App\Http\Requests\PriceListItem;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class UpdatePriceListItemRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'route_template_id' => ['sometimes', 'nullable', 'integer', Rule::exists('route_templates', 'id')->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId()))],
            'vehicle_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('vehicle_types', 'id')->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId()))],
            'cargo_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('cargo_types', 'id')->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId()))],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'price_unit' => ['sometimes', 'in:per_trip,per_km,per_ton'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
