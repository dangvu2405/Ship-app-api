<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('trip');

        return [
            'code' => 'sometimes|string|max:50|unique:trips,code,' . $id,
            'customer_id' => 'sometimes|exists:customers,id',
            'driver_id' => 'sometimes|exists:employees,id',
            'vehicle_id' => 'sometimes|exists:vehicles,id',
            'start_point' => 'sometimes|string|max:255',
            'end_point' => 'sometimes|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'price' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
        ];
    }
}
