<?php

declare(strict_types=1);

namespace App\Http\Requests\Position;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('position');

        return [
            'code' => 'sometimes|string|max:50|unique:positions,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'base_salary' => 'sometimes|numeric|min:0',
            'level' => 'nullable|integer|min:0',
        ];
    }
}
