<?php

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
            'driver_id' => 'nullable|exists:employees,id',
            'type' => 'sometimes|in:fuel,maintenance,repair,toll,parking,other',
            'amount' => 'sometimes|numeric|min:0',
            'note' => 'nullable|string',
            'expense_date' => 'sometimes|date',
        ];
    }
}
