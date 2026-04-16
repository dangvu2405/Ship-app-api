<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'office_id' => 'required|exists:offices,id',
            'plate_number' => 'required|string|max:20|unique:vehicles,plate_number',
            'type' => 'required|in:truck,van,car,motorcycle',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:2100',
            'capacity' => 'nullable|integer|min:0',
            'status' => 'required|in:active,maintenance,inactive',
            'image_front' => 'nullable|url|max:255',
            'image_back' => 'nullable|url|max:255',
            'image_side' => 'nullable|url|max:255',
            'image_other' => 'nullable|url|max:255',
        ];
    }
}
