<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) $this->route('trip');

        return [
            'code' => 'sometimes|string|max:50|unique:trips,code,' . $id,
            'customer_id' => 'sometimes|exists:customers,id',
            'driver_id' => 'sometimes|exists:drivers,id',
            'vehicle_id' => 'sometimes|exists:vehicles,id',
            'start_point' => 'sometimes|string|max:255',
            'end_point' => 'sometimes|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'price' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tripId = (int) $this->route('trip');
            $trip = Trip::query()->find($tripId);

            if (! $trip) {
                return;
            }

            $startPoint = $this->input('start_point', $trip->start_point);
            $endPoint = $this->input('end_point', $trip->end_point);
            if ((string) $startPoint !== '' && (string) $endPoint !== '' && $startPoint === $endPoint) {
                $validator->errors()->add('end_point', __('api.validation.trip_end_point_must_differ'));
            }

            $newStatus = (string) $this->input('status', $trip->status);
            $allowedTransitions = [
                'pending' => ['pending', 'in_progress', 'cancelled'],
                'in_progress' => ['in_progress', 'completed', 'cancelled'],
                'completed' => ['completed'],
                'cancelled' => ['cancelled'],
            ];

            if (! in_array($newStatus, $allowedTransitions[$trip->status] ?? [], true)) {
                $validator->errors()->add('status', __('api.validation.trip_invalid_status_transition'));
            }

            $effectiveStartTime = $this->input('start_time', $trip->start_time);
            $effectiveEndTime = $this->input('end_time', $trip->end_time);

            if ($newStatus === 'in_progress' && empty($effectiveStartTime)) {
                $validator->errors()->add('start_time', __('api.validation.trip_start_time_required_for_in_progress'));
            }

            if ($newStatus === 'completed') {
                if (empty($effectiveStartTime)) {
                    $validator->errors()->add('start_time', __('api.validation.trip_start_time_required_for_completed'));
                }

                if (empty($effectiveEndTime)) {
                    $validator->errors()->add('end_time', __('api.validation.trip_end_time_required_for_completed'));
                }
            }

            $driverId = (int) $this->input('driver_id', $trip->driver_id);
            if ($driverId > 0 && Trip::query()
                ->where('id', '!=', $tripId)
                ->where('driver_id', $driverId)
                ->where('status', 'in_progress')
                ->exists()) {
                $validator->errors()->add('driver_id', __('api.validation.trip_driver_in_progress_conflict'));
            }

            $vehicleId = (int) $this->input('vehicle_id', $trip->vehicle_id);
            if ($vehicleId > 0 && Trip::query()
                ->where('id', '!=', $tripId)
                ->where('vehicle_id', $vehicleId)
                ->where('status', 'in_progress')
                ->exists()) {
                $validator->errors()->add('vehicle_id', __('api.validation.trip_vehicle_in_progress_conflict'));
            }
        });
    }
}
