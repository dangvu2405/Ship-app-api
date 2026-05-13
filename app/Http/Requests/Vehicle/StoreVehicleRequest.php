<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('vehicles', 'create');
    }

    public function rules(): array
    {
        return [
            'plate_number' => ['required', 'string', 'max:255', Rule::unique('vehicles', 'plate_number')],
            'type' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|integer|min:1900|max:'.(date('Y') + 1),
            'capacity' => 'nullable|integer|min:0',
            'max_load_ton' => 'nullable|numeric|min:0',
            'current_odometer_km' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:active,maintenance,inactive,broken',
            'vehicle_type_id' => ['nullable', 'integer', $this->existsInCompany('vehicle_types')],
        ];
    }
}
