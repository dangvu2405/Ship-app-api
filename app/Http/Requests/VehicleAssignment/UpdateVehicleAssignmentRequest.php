<?php

declare(strict_types=1);

namespace App\Http\Requests\VehicleAssignment;

use App\Models\VehicleAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateVehicleAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => 'sometimes|exists:vehicles,id',
            'driver_id' => 'sometimes|exists:drivers,id',
            'from_date' => 'sometimes|date',
            'to_date' => 'nullable|date',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $assignmentId = (int) $this->route('vehicle_assignment');
            $assignment = VehicleAssignment::query()->find($assignmentId);

            if (! $assignment) {
                return;
            }

            $vehicleId = (int) $this->input('vehicle_id', $assignment->vehicle_id);
            $driverId = (int) $this->input('driver_id', $assignment->driver_id);
            $fromDate = (string) $this->input('from_date', $assignment->from_date?->format('Y-m-d'));
            $toDateInput = $this->input('to_date', $assignment->to_date?->format('Y-m-d'));
            $toDate = $toDateInput ?: '9999-12-31';

            if ($toDate !== '9999-12-31' && $fromDate > $toDate) {
                $validator->errors()->add('to_date', __('api.validation.vehicle_assignment_to_date_invalid'));

                return;
            }

            $vehicleOverlapped = VehicleAssignment::query()
                ->where('id', '!=', $assignmentId)
                ->where('vehicle_id', $vehicleId)
                ->whereDate('from_date', '<=', $toDate)
                ->where(function ($query) use ($fromDate): void {
                    $query->whereNull('to_date')->orWhereDate('to_date', '>=', $fromDate);
                })
                ->exists();

            if ($vehicleOverlapped) {
                $validator->errors()->add('vehicle_id', __('api.validation.vehicle_assignment_overlap_vehicle'));
            }

            $driverOverlapped = VehicleAssignment::query()
                ->where('id', '!=', $assignmentId)
                ->where('driver_id', $driverId)
                ->whereDate('from_date', '<=', $toDate)
                ->where(function ($query) use ($fromDate): void {
                    $query->whereNull('to_date')->orWhereDate('to_date', '>=', $fromDate);
                })
                ->exists();

            if ($driverOverlapped) {
                $validator->errors()->add('driver_id', __('api.validation.vehicle_assignment_overlap_driver'));
            }
        });
    }
}
