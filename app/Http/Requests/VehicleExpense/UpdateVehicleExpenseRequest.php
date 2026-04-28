<?php

declare(strict_types=1);

namespace App\Http\Requests\VehicleExpense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => 'sometimes|exists:vehicles,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'type' => 'sometimes|in:fuel,maintenance,repair,toll,parking,other',
            'amount' => 'sometimes|numeric|min:0|max:999999999',
            'note' => 'nullable|string|max:500',
            'expense_date' => 'sometimes|date|before_or_equal:today',
        ];
    }
}
