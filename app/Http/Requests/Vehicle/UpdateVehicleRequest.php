<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

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
            'plate_number' => 'sometimes|string|max:20|unique:vehicles,plate_number,' . $id,
            'type' => 'sometimes|in:truck,van,car,motorcycle',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'capacity' => 'nullable|integer|min:0',
            'status' => 'sometimes|in:active,maintenance,inactive',
            'image_front' => 'nullable|url|max:255',
            'image_back' => 'nullable|url|max:255',
            'image_side' => 'nullable|url|max:255',
            'image_other' => 'nullable|url|max:255',
        ];
    }
}
