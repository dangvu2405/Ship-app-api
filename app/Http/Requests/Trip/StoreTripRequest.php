<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use App\Models\Trip;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\Customer;
use App\Models\LeaveRequest;
use App\Models\Quotation;
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
            'route_template_id' => 'nullable|exists:route_templates,id',
            'cargo_type_id' => 'nullable|exists:cargo_types,id',
            'start_point' => 'required|string|max:255',
            'end_point' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'price' => 'nullable|numeric|min:0',
            'base_price' => 'nullable|numeric|min:0',
            'surcharge_amount' => 'nullable|numeric|min:0',
            'total_revenue' => 'nullable|numeric|min:0',
            'status' => 'required|in:pending,assigned,in_progress,in_transit,delivered,completed,cancelled',
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

            if ($vehicleId) {
                $vehicleStatus = Vehicle::query()->whereKey($vehicleId)->value('status');
                if (in_array($vehicleStatus, ['maintenance', 'broken'], true)) {
                    $validator->errors()->add('vehicle_id', 'Xe đang bảo dưỡng hoặc hỏng, không thể phân công.');
                }
            }

            if ($driverId && $this->filled('start_time')) {
                $tripDate = date('Y-m-d', strtotime((string) $this->input('start_time')));
                $onLeave = LeaveRequest::query()
                    ->where('driver_id', $driverId)
                    ->where('status', 'approved')
                    ->whereDate('from_date', '<=', $tripDate)
                    ->whereDate('to_date', '>=', $tripDate)
                    ->exists();

                if ($onLeave) {
                    $validator->errors()->add('driver_id', 'Tài xế đang nghỉ phép đã duyệt trong ngày chạy chuyến.');
                }
            }

            $quotationId = $this->input('quotation_id');
            if ($quotationId !== null) {
                $status = Quotation::query()->whereKey($quotationId)->value('status');
                if ($status !== 'approved') {
                    $validator->errors()->add('quotation_id', 'Quotation must be approved before creating trip.');
                }
            }

            $customerCompanyId = $this->filled('customer_id')
                ? Customer::withoutGlobalScope('tenant')->whereKey($this->input('customer_id'))->value('company_id')
                : null;
            $driverCompanyId = $this->filled('driver_id')
                ? Driver::withoutGlobalScope('tenant')->whereKey($this->input('driver_id'))->value('company_id')
                : null;
            $vehicleCompanyId = $this->filled('vehicle_id')
                ? Vehicle::withoutGlobalScope('tenant')->whereKey($this->input('vehicle_id'))->value('company_id')
                : null;

            $companyIds = array_values(array_unique(array_filter([
                $customerCompanyId,
                $driverCompanyId,
                $vehicleCompanyId,
            ], static fn ($value) => $value !== null)));

            if (count($companyIds) > 1) {
                $validator->errors()->add('customer_id', 'Cross-company references are not allowed for trip creation.');
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
