<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
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

        $record = MaintenanceRecord::query()->create(array_merge($validated, [
            'company_id' => $this->companyId($request),
            'vehicle_id' => $vehicleId,
            'status' => $validated['status'] ?? 'open',
        ]));

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
