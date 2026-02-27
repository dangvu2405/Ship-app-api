<?php

namespace App\Http\Requests\Position;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:positions,code',
            'name' => 'required|string|max:255',
            'base_salary' => 'required|numeric|min:0',
            'level' => 'nullable|integer|min:0',
        ];
    }
}
