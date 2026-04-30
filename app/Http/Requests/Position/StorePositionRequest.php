<?php

declare(strict_types=1);

namespace App\Http\Requests\Position;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->filled('company_id')) {
            return;
        }

        $tenant_id = app(TenantContext::class)->getCompanyId();
        if ($tenant_id !== null && $tenant_id > 0) {
            $this->merge(['company_id' => $tenant_id]);

            return;
        }

    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'code' => 'required|string|max:50|unique:positions,code',
            'name' => 'required|string|max:255',
            'base_salary' => 'required|numeric|min:0|max:999999999',
            'level' => 'nullable|integer|min:0|max:10',
        ];
    }
}
