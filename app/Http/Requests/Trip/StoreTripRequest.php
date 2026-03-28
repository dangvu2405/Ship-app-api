<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:trips,code',
            'customer_id' => 'required|exists:customers,id',
            'driver_id' => 'required|exists:employees,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'start_point' => 'required|string|max:255',
            'end_point' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'price' => 'nullable|numeric|min:0',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ];
    }
}
