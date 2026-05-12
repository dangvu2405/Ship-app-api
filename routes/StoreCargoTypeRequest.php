<?php

declare(strict_types=1);

namespace App\Http\Requests\CargoType;

use Illuminate\Foundation\Http\FormRequest;

class StoreCargoTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'requires_special_vehicle' => ['nullable', 'boolean'],
            'special_requirements' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}