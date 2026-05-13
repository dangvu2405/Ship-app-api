<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use App\Http\Requests\AppFormRequest;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVehicleRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('vehicles', 'edit');
    }

    public function rules(): array
    {
        $vehicle = $this->route('vehicle');
        $vehicleId = $vehicle instanceof Vehicle ? $vehicle->getKey() : $vehicle;

        return [
            'plate_number' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('vehicles', 'plate_number')->ignore($vehicleId)],
            'type' => 'sometimes|required|string|max:255',
            'brand' => 'sometimes|nullable|string|max:255',
            'model' => 'sometimes|nullable|string|max:255',
            'year' => 'sometimes|nullable|integer|min:1900|max:'.(date('Y') + 1),
            'capacity' => 'sometimes|nullable|integer|min:0',
            'max_load_ton' => 'sometimes|nullable|numeric|min:0',
            'current_odometer_km' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|nullable|string|in:active,maintenance,inactive,broken',
            'vehicle_type_id' => ['sometimes', 'nullable', 'integer', $this->existsInCompany('vehicle_types')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('status') || ! in_array((string) $this->input('status'), ['maintenance', 'broken'], true)) {
                return;
            }

            $vehicle = $this->route('vehicle');
            $vehicleId = $vehicle instanceof Vehicle ? $vehicle->getKey() : $vehicle;
            if ($vehicleId === null) {
                return;
            }

            $companyId = $this->tenantCompanyId();
            $hasInProgressTrip = Trip::query()
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                ->where('vehicle_id', (int) $vehicleId)
                ->where('status', 'in_progress')
                ->exists();

            if ($hasInProgressTrip) {
                $validator->errors()->add('status', 'Vehicle cannot be moved to maintenance while trip is in progress.');
            }
        });
    }
}
