<?php

declare(strict_types=1);

namespace App\Http\Requests\VehicleAssignment;

use App\Http\Requests\AppFormRequest;
use App\Models\VehicleAssignment;
use Illuminate\Validation\Validator;

class StoreVehicleAssignmentRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('vehicles', 'edit');
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', $this->existsInCompany('vehicles')],
            'driver_id' => ['required', 'integer', $this->existsInCompany('drivers')],
            'from_date' => 'required|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $companyId = $this->tenantCompanyId();
            $vehicleId = (int) $this->input('vehicle_id');
            $driverId = (int) $this->input('driver_id');
            $fromDate = (string) $this->input('from_date');
            $toDate = $this->input('to_date');

            if (! $vehicleId || ! $driverId || $fromDate === '') {
                return;
            }

            $toDate = $toDate ?: '9999-12-31';

            $vehicleOverlapped = VehicleAssignment::query()
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
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
