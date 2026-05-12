<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use App\Http\Requests\AppFormRequest;
use App\Models\Trip;
use Illuminate\Validation\Validator;

class UpdateTripRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('orders', 'edit');
    }

    public function rules(): array
    {
        $id = (int) $this->route('trip');

        return [
            'code' => 'sometimes|string|max:50|unique:trips,code,'.$id,
            'customer_id' => ['sometimes', 'integer', $this->existsInCompany('customers')],
            'driver_id' => ['sometimes', 'integer', $this->existsInCompany('drivers')],
            'vehicle_id' => ['sometimes', 'integer', $this->existsInCompany('vehicles')],
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
            $companyId = $this->tenantCompanyId();
            $tripId = (int) $this->route('trip');
            $trip = Trip::query()
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                ->find($tripId);

            if (! $trip) {
                return;
            }

            $startPoint = $this->input('start_point', $trip->start_point);
            $endPoint = $this->input('end_point', $trip->end_point);
            if ((string) $startPoint !== '' && (string) $endPoint !== '' && $startPoint === $endPoint) {
                $validator->errors()->add('end_point', 'Điểm đến phải khác điểm đi.');
            }

            $newStatus = (string) $this->input('status', $trip->status);
            $allowedTransitions = [
                'pending' => ['pending', 'in_progress', 'cancelled'],
                'in_progress' => ['in_progress', 'completed', 'cancelled'],
                'completed' => ['completed'],
                'cancelled' => ['cancelled'],
            ];

            if (! in_array($newStatus, $allowedTransitions[$trip->status] ?? [], true)) {
                $validator->errors()->add('status', 'Chuyển trạng thái chuyến đi không hợp lệ.');
            }

            $effectiveStartTime = $this->input('start_time', $trip->start_time);
            $effectiveEndTime = $this->input('end_time', $trip->end_time);

            if ($newStatus === 'in_progress' && empty($effectiveStartTime)) {
                $validator->errors()->add('start_time', 'start_time là bắt buộc khi trạng thái là in_progress.');
            }

            if ($newStatus === 'completed') {
                if (empty($effectiveStartTime)) {
                    $validator->errors()->add('start_time', 'start_time là bắt buộc khi trạng thái là completed.');
                }

                if (empty($effectiveEndTime)) {
                    $validator->errors()->add('end_time', 'end_time là bắt buộc khi trạng thái là completed.');
                }
            }

            $driverId = (int) $this->input('driver_id', $trip->driver_id);
            if ($driverId > 0 && Trip::query()
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                ->where('id', '!=', $tripId)
                ->where('driver_id', $driverId)
                ->where('status', 'in_progress')
                ->exists()) {
                $validator->errors()->add('driver_id', 'Tài xế đang có chuyến in_progress khác.');
            }

            $vehicleId = (int) $this->input('vehicle_id', $trip->vehicle_id);
            if ($vehicleId > 0 && Trip::query()
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                ->where('id', '!=', $tripId)
                ->where('vehicle_id', $vehicleId)
                ->where('status', 'in_progress')
                ->exists()) {
                $validator->errors()->add('vehicle_id', 'Xe đang có chuyến in_progress khác.');
            }
        });
    }
}
