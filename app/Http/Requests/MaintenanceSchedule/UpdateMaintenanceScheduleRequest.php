<?php

declare(strict_types=1);

namespace App\Http\Requests\MaintenanceSchedule;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class UpdateMaintenanceScheduleRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'vehicle_id' => ['sometimes', 'integer', Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId()))],
            'task_name' => ['sometimes', 'string', 'max:200'],
            'interval_type' => ['sometimes', 'in:by_km,by_days,both'],
            'interval_km' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'interval_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'last_done_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'last_done_date' => ['sometimes', 'nullable', 'date'],
            'next_due_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'next_due_date' => ['sometimes', 'nullable', 'date'],
            'alert_before_km' => ['sometimes', 'integer', 'min:0'],
            'alert_before_days' => ['sometimes', 'integer', 'min:0'],
            'estimated_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
