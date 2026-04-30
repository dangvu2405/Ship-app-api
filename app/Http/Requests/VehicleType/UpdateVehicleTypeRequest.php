<?php

declare(strict_types=1);

namespace App\Http\Requests\VehicleType;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class UpdateVehicleTypeRequest extends AppFormRequest
{
    public function rules(): array
    {
        $vehicleTypeId = (int) $this->route('vehicle_type');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('vehicle_types', 'name')
                ->ignore($vehicleTypeId)
                ->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId()))],
            'max_load_ton' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'volume_m3' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'required_license_class' => ['sometimes', 'nullable', 'string', 'max:10'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
