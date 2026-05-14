<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MaintenanceController extends BaseController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function schedules(Request $request): JsonResponse
    {
        $query = MaintenanceSchedule::query()
            ->where('company_id', $this->companyId($request))
            ->when($request->integer('vehicle_id') > 0, fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->latest('id');

        return $this->successResponse($query->paginate($this->perPage($request)), 'OK');
    }

    public function records(Request $request): JsonResponse
    {
        $query = MaintenanceRecord::query()
            ->where('company_id', $this->companyId($request))
            ->when($request->integer('vehicle_id') > 0, fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->latest('id');

        return $this->successResponse($query->paginate($this->perPage($request)), 'OK');
    }

    public function storeRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['nullable', 'integer'],
            'maintenance_schedule_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', 'in:scheduled,unscheduled'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
            'started_date' => ['required', 'date'],
            'completed_date' => ['nullable', 'date'],
            'garage_name' => ['nullable', 'string', 'max:200'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'file_url' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'in:open,in_progress,completed'],
            'notes' => ['nullable', 'string'],
        ]);

        $vehicleId = $request->integer('vehicle_id') ?: (int) ($validated['vehicle_id'] ?? 0);
        if ($vehicleId <= 0) {
            return $this->validationErrorResponse(['vehicle_id' => ['vehicle_id is required.']]);
        }

        $status = $validated['status'] ?? 'open';
        $record = MaintenanceRecord::query()->create(array_merge($validated, [
            'company_id' => $this->companyId($request),
            'vehicle_id' => $vehicleId,
            'status' => $status,
        ]));

        // Mark vehicle as under maintenance unless record is already completed on creation
        if ($status !== 'completed') {
            Vehicle::query()->where('id', $vehicleId)->update(['status' => 'maintenance']);
        }

        return $this->successResponse($record, 'OK', 201);
    }

    public function completeRecord(Request $request, MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        if ((int) $maintenanceRecord->company_id !== $this->companyId($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'odometer_km' => ['required', 'numeric', 'min:0'],
        ]);

        $maintenanceRecord->update([
            'status' => 'completed',
            'completed_date' => now()->toDateString(),
            'odometer_km' => $validated['odometer_km'],
        ]);

        // Restore vehicle to active only if no other open maintenance records exist for it
        $hasOtherOpen = MaintenanceRecord::query()
            ->where('vehicle_id', $maintenanceRecord->vehicle_id)
            ->where('id', '!=', $maintenanceRecord->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->exists();

        if (! $hasOtherOpen) {
            Vehicle::query()->where('id', $maintenanceRecord->vehicle_id)->update(['status' => 'active']);
        }

        return $this->successResponse($maintenanceRecord->refresh(), 'OK');
    }

    private function companyId(Request $request): int
    {
        return (int) ($this->tenantContext->getCompanyId() ?? $request->user()?->getAttribute('company_id'));
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
