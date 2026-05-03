<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:trips,code',
            'customer_id' => 'required|exists:customers,id',
            'driver_id' => 'required|exists:drivers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'start_point' => 'required|string|max:255',
            'end_point' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'price' => 'nullable|numeric|min:0',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('start_point') !== ''
                && (string) $this->input('end_point') !== ''
                && $this->input('start_point') === $this->input('end_point')) {
                $validator->errors()->add('end_point', 'Điểm đến phải khác điểm đi.');
            }

            $driverId = $this->input('driver_id');
            $vehicleId = $this->input('vehicle_id');

            if ($driverId && Trip::query()->where('driver_id', $driverId)->where('status', 'in_progress')->exists()) {
                $validator->errors()->add('driver_id', 'Tài xế đang có chuyến in_progress, không thể tạo chuyến mới.');
            }

            if ($vehicleId && Trip::query()->where('vehicle_id', $vehicleId)->where('status', 'in_progress')->exists()) {
                $validator->errors()->add('vehicle_id', 'Xe đang có chuyến in_progress, không thể tạo chuyến mới.');
            }

            if ($this->input('status') === 'in_progress' && ! $this->filled('start_time')) {
                $validator->errors()->add('start_time', 'start_time là bắt buộc khi trạng thái là in_progress.');
            }

            if ($this->input('status') === 'completed') {
                if (! $this->filled('start_time')) {
                    $validator->errors()->add('start_time', 'start_time là bắt buộc khi trạng thái là completed.');
                }

                if (! $this->filled('end_time')) {
                    $validator->errors()->add('end_time', 'end_time là bắt buộc khi trạng thái là completed.');
                }
            }
        });
    }
}
