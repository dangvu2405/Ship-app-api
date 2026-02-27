<?php

namespace App\Http\Requests\VehicleAssignment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => 'sometimes|exists:vehicles,id',
            'driver_id' => 'sometimes|exists:employees,id',
            'from_date' => 'sometimes|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ];
    }
}
