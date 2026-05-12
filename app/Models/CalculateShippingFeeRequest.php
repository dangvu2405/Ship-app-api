<?php

declare(strict_types=1);

namespace App\Http\Requests\ShippingFee;

use Illuminate\Foundation\Http\FormRequest;

class CalculateShippingFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Được bảo vệ sẵn qua middleware auth:sanctum
    }

    public function rules(): array
    {
        return [
            'origin' => ['required', 'string'],
            'destination' => ['required', 'string'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
        ];
    }
}