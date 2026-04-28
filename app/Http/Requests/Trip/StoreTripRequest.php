<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use App\Http\Requests\AppFormRequest;
use App\Models\Trip;
use Illuminate\Validation\Validator;

class StoreTripRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'code'        => 'required|string|max:50|unique:trips,code',
            'customer_id' => 'required|exists:customers,id',
            'driver_id'   => 'required|exists:drivers,id',
            'vehicle_id'  => 'required|exists:vehicles,id',
            'start_point' => 'required|string|max:255',
            'end_point'   => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'start_time'  => 'nullable|date',
            'end_time'    => 'nullable|date|after_or_equal:start_time',
            'price'       => 'nullable|numeric|min:0',
            'status'      => 'required|in:pending,in_progress,completed,cancelled',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('start_point') !== ''
                && (string) $this->input('end_point') !== ''
                && $this->input('start_point') === $this->input('end_point')) {
                $validator->errors()->add('end_point', __('api.validation.trip_end_point_must_differ'));
            }

            $driverId  = $this->input('driver_id');
            $vehicleId = $this->input('vehicle_id');

            if ($driverId && Trip::query()->where('driver_id', $driverId)->where('status', 'in_progress')->exists()) {
                $validator->errors()->add('driver_id', __('api.validation.trip_driver_in_progress_conflict'));
            }

            if ($vehicleId && Trip::query()->where('vehicle_id', $vehicleId)->where('status', 'in_progress')->exists()) {
                $validator->errors()->add('vehicle_id', __('api.validation.trip_vehicle_in_progress_conflict'));
            }

            if ($this->input('status') === 'in_progress' && ! $this->filled('start_time')) {
                $validator->errors()->add('start_time', __('api.validation.trip_start_time_required_for_in_progress'));
            }

            if ($this->input('status') === 'completed') {
                if (! $this->filled('start_time')) {
                    $validator->errors()->add('start_time', __('api.validation.trip_start_time_required_for_completed'));
                }

                if (! $this->filled('end_time')) {
                    $validator->errors()->add('end_time', __('api.validation.trip_end_time_required_for_completed'));
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required' => __('api.validation.required'),
            'code.max' => __('api.validation.max.string'),
            'code.unique' => __('api.validation.unique'),
            'customer_id.required' => __('api.validation.required'),
            'customer_id.exists' => __('api.validation.exists'),
            'driver_id.required' => __('api.validation.required'),
            'driver_id.exists' => __('api.validation.exists'),
            'vehicle_id.required' => __('api.validation.required'),
            'vehicle_id.exists' => __('api.validation.exists'),
            'start_point.required' => __('api.validation.required'),
            'end_point.required' => __('api.validation.required'),
            'distance_km.numeric' => __('api.validation.numeric'),
            'start_time.date' => __('api.validation.date'),
            'end_time.date' => __('api.validation.date'),
            'end_time.after_or_equal' => __('api.validation.after_or_equal'),
            'price.numeric' => __('api.validation.numeric'),
            'status.required' => __('api.validation.required'),
            'status.in' => __('api.validation.in'),
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => __('api.attributes.trip_code'),
            'customer_id' => __('api.attributes.trip_customer_id'),
            'driver_id' => __('api.attributes.trip_driver_id'),
            'vehicle_id' => __('api.attributes.trip_vehicle_id'),
            'start_point' => __('api.attributes.trip_start_point'),
            'end_point' => __('api.attributes.trip_end_point'),
            'distance_km' => __('api.attributes.trip_distance_km'),
            'start_time' => __('api.attributes.trip_start_time'),
            'end_time' => __('api.attributes.trip_end_time'),
            'price' => __('api.attributes.trip_price'),
            'status' => __('api.attributes.trip_status'),
        ];
    }
}
