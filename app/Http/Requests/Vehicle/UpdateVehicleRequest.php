<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('vehicle');

        return [
            'office_id' => 'sometimes|exists:offices,id',
            'plate_number' => 'sometimes|string|max:20|unique:vehicles,plate_number,'.$id,
            'type' => 'sometimes|in:truck,van,car,motorcycle',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'capacity' => 'nullable|integer|min:0',
            'current_odometer_km' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:active,maintenance,inactive,broken,out_of_service',
            'image_front' => 'nullable|url|max:255',
            'image_back' => 'nullable|url|max:255',
            'image_side' => 'nullable|url|max:255',
            'image_other' => 'nullable|url|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('status') || ! in_array((string) $this->input('status'), ['maintenance', 'broken'], true)) {
                return;
            }

            $vehicleId = $this->route('vehicle');
            if ($vehicleId === null) {
                return;
            }

            $hasInProgressTrip = Trip::query()
                ->where('vehicle_id', (int) $vehicleId)
                ->where('status', 'in_progress')
                ->exists();

            if ($hasInProgressTrip) {
                $validator->errors()->add('status', 'Vehicle cannot be moved to maintenance while trip is in progress.');
            }
        });
    }
}
