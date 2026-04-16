<?php

declare(strict_types=1);

namespace App\Http\Requests\Position;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if (! $this->filled('company_id') && $this->user()?->driver?->company_id !== null) {
            $this->merge(['company_id' => $this->user()->driver->company_id]);
        }
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'code' => 'required|string|max:50|unique:positions,code',
            'name' => 'required|string|max:255',
            'base_salary' => 'required|numeric|min:0',
            'level' => 'nullable|integer|min:0',
        ];
    }
}
