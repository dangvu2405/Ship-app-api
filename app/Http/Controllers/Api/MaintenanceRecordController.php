<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\MaintenanceRecord\StoreMaintenanceRecordRequest;
use App\Http\Requests\MaintenanceRecord\UpdateMaintenanceRecordRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MaintenanceRecordController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'completed_date', 'odometer_km', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = MaintenanceRecord::query();
        $result = $this->indexQuery($request, $query, ['title'], ['vehicle_id' => 'vehicle_id', 'type' => 'type', 'status' => 'status']);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreMaintenanceRecordRequest $request): JsonResponse
    {
        $record = MaintenanceRecord::query()->create($request->validated());
        $this->syncScheduleAfterCompletion($record);

        return $this->successResponse($record, 'Maintenance record created successfully', 201);
    }

    public function show(string $maintenanceRecord): JsonResponse
    {
        $model = MaintenanceRecord::query()->find($maintenanceRecord);
        if ($model === null) {
            return $this->notFoundResponse('Maintenance record not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateMaintenanceRecordRequest $request, string $maintenanceRecord): JsonResponse
    {
        $model = MaintenanceRecord::query()->find($maintenanceRecord);
        if ($model === null) {
            return $this->notFoundResponse('Maintenance record not found');
        }

        $model->update($request->validated());
        $this->syncScheduleAfterCompletion($model->fresh());

        return $this->successResponse($model->fresh(), 'Maintenance record updated successfully');
    }

    public function destroy(string $maintenanceRecord): JsonResponse
    {
        $model = MaintenanceRecord::query()->find($maintenanceRecord);
        if ($model === null) {
            return $this->notFoundResponse('Maintenance record not found');
        }

        $model->delete();

        return $this->successResponse(null, 'Maintenance record deleted successfully');
    }

    private function syncScheduleAfterCompletion(MaintenanceRecord $record): void
    {
        if ($record->maintenance_schedule_id === null || $record->status !== 'completed') {
            return;
        }

        $schedule = MaintenanceSchedule::query()->find($record->maintenance_schedule_id);
        if ($schedule === null) {
            return;
        }

        $updates = [
            'last_done_km' => $record->odometer_km,
            'last_done_date' => $record->completed_date,
        ];

        if ($schedule->interval_km !== null && $record->odometer_km !== null) {
            $updates['next_due_km'] = (float) $record->odometer_km + (int) $schedule->interval_km;
        }

        if ($schedule->interval_days !== null && $record->completed_date !== null) {
            $updates['next_due_date'] = $record->completed_date->copy()->addDays((int) $schedule->interval_days);
        }

        $schedule->update($updates);
    }
}
