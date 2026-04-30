<?php

declare(strict_types=1);

namespace App\Http\Requests\MaintenanceRecord;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class UpdateMaintenanceRecordRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'vehicle_id' => ['sometimes', 'integer', Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId()))],
            'maintenance_schedule_id' => ['sometimes', 'nullable', 'integer', Rule::exists('maintenance_schedules', 'id')->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId()))],
            'type' => ['sometimes', 'in:scheduled,unscheduled'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'odometer_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'started_date' => ['sometimes', 'nullable', 'date'],
            'completed_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:started_date'],
            'garage_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'total_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'file_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:draft,in_progress,completed,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
