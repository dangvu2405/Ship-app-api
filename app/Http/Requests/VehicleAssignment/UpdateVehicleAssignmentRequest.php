<?php

declare(strict_types=1);

namespace App\Http\Requests\VehicleAssignment;

use App\Http\Requests\AppFormRequest;
use App\Models\VehicleAssignment;
use Illuminate\Validation\Validator;

class UpdateVehicleAssignmentRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('vehicles', 'edit');
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['sometimes', 'integer', $this->existsInCompany('vehicles')],
            'driver_id' => ['sometimes', 'integer', $this->existsInCompany('drivers')],
            'from_date' => 'sometimes|date',
            'to_date' => 'nullable|date',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $companyId = $this->tenantCompanyId();
            $assignmentId = (int) $this->route('vehicle_assignment');
            $assignment = VehicleAssignment::query()
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                ->find($assignmentId);

            if (! $assignment) {
                return;
            }

            $vehicleId = (int) $this->input('vehicle_id', $assignment->vehicle_id);
            $driverId = (int) $this->input('driver_id', $assignment->driver_id);
            $fromDate = (string) $this->input('from_date', $assignment->from_date?->format('Y-m-d'));
            $toDateInput = $this->input('to_date', $assignment->to_date?->format('Y-m-d'));
            $toDate = $toDateInput ?: '9999-12-31';

            if ($toDate !== '9999-12-31' && $fromDate > $toDate) {
                $validator->errors()->add('to_date', 'to_date phải sau hoặc bằng from_date.');

                return;
            }

            $vehicleOverlapped = VehicleAssignment::query()
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                ->where('id', '!=', $assignmentId)
                ->where('vehicle_id', $vehicleId)
                ->whereDate('from_date', '<=', $toDate)
                ->where(function ($query) use ($fromDate): void {
                    $query->whereNull('to_date')->orWhereDate('to_date', '>=', $fromDate);
                })
                ->exists();

            if ($vehicleOverlapped) {
                $validator->errors()->add('vehicle_id', 'Xe đã có phân công trùng thời gian.');
            }

            $driverOverlapped = VehicleAssignment::query()
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                ->where('id', '!=', $assignmentId)
                ->where('driver_id', $driverId)
                ->whereDate('from_date', '<=', $toDate)
                ->where(function ($query) use ($fromDate): void {
                    $query->whereNull('to_date')->orWhereDate('to_date', '>=', $fromDate);
                })
                ->exists();

            if ($driverOverlapped) {
                $validator->errors()->add('driver_id', 'Tài xế đã có phân công trùng thời gian.');
            }
        });
    }
}
