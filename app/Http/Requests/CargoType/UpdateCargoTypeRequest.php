<?php

declare(strict_types=1);

namespace App\Http\Requests\CargoType;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class UpdateCargoTypeRequest extends AppFormRequest
{
    public function rules(): array
    {
        $cargoTypeId = (int) $this->route('cargo_type');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('cargo_types', 'name')
                ->ignore($cargoTypeId)
                ->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId()))],
            'requires_special_vehicle' => ['sometimes', 'boolean'],
            'special_requirements' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
