<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by Policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contact_name' => ['nullable', 'string', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'cargo_type_id' => ['nullable', 'integer', 'exists:cargo_types,id'],
            'cargo_description' => ['nullable', 'string'],
            'cargo_quantity' => ['nullable', 'numeric'],
            'cargo_unit' => ['nullable', 'string', 'max:50'],
            'cargo_weight_ton' => ['nullable', 'numeric'],
            'cargo_notes' => ['nullable', 'string'],
            'received_date' => ['required', 'date'],
            'scheduled_date' => ['required', 'date'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:bank_transfer,cash,credit'],
            'internal_notes' => ['nullable', 'string'],
            
            // Stops validation
            'stops' => ['nullable', 'array', 'min:1'],
            'stops.*.stop_type' => ['required', 'string', 'in:pickup,delivery'],
            'stops.*.sequence' => ['required', 'integer', 'min:1'],
            'stops.*.address' => ['required', 'string'],
            'stops.*.location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'stops.*.contact_name' => ['nullable', 'string', 'max:200'],
            'stops.*.contact_phone' => ['nullable', 'string', 'max:20'],
            'stops.*.scheduled_time' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'stops.*.notes' => ['nullable', 'string'],

            // Surcharges validation
            'surcharges' => ['nullable', 'array'],
            'surcharges.*.name' => ['required', 'string', 'max:200'],
            'surcharges.*.amount' => ['required', 'numeric', 'min:0'],
            'surcharges.*.notes' => ['nullable', 'string'],
        ];
    }
}
