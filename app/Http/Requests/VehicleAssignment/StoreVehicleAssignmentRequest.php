<?php

declare(strict_types=1);

namespace App\Http\Requests\VehicleAssignment;

use App\Models\VehicleAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVehicleAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:employees,id',
            'from_date' => 'required|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $vehicleId = (int) $this->input('vehicle_id');
            $driverId = (int) $this->input('driver_id');
            $fromDate = (string) $this->input('from_date');
            $toDate = $this->input('to_date');

            if (! $vehicleId || ! $driverId || $fromDate === '') {
                return;
            }

            $toDate = $toDate ?: '9999-12-31';

            $vehicleOverlapped = VehicleAssignment::query()
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
