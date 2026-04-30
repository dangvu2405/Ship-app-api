<?php

declare(strict_types=1);

namespace App\Http\Requests\MaintenanceSchedule;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class StoreMaintenanceScheduleRequest extends AppFormRequest
{
    public function prepareForValidation(): void
    {
        if ($this->filled('company_id')) {
            return;
        }

        $companyId = $this->tenantCompanyId();
        if ($companyId !== null) {
            $this->merge(['company_id' => $companyId]);
        }
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('company_id', $this->input('company_id')))],
            'task_name' => ['required', 'string', 'max:200'],
            'interval_type' => ['required', 'in:by_km,by_days,both'],
            'interval_km' => ['nullable', 'integer', 'min:1'],
            'interval_days' => ['nullable', 'integer', 'min:1'],
            'last_done_km' => ['nullable', 'numeric', 'min:0'],
            'last_done_date' => ['nullable', 'date'],
            'next_due_km' => ['nullable', 'numeric', 'min:0'],
            'next_due_date' => ['nullable', 'date'],
            'alert_before_km' => ['sometimes', 'integer', 'min:0'],
            'alert_before_days' => ['sometimes', 'integer', 'min:0'],
            'estimated_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
