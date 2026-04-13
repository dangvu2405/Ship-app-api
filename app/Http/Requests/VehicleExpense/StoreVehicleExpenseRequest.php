<?php

declare(strict_types=1);

namespace App\Http\Requests\VehicleExpense;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'nullable|exists:drivers,id',
            'type' => 'required|in:fuel,maintenance,repair,toll,parking,other',
            'amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
            'expense_date' => 'required|date',
        ];
    }
}
