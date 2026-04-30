<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\MaintenanceSchedule\StoreMaintenanceScheduleRequest;
use App\Http\Requests\MaintenanceSchedule\UpdateMaintenanceScheduleRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\MaintenanceSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MaintenanceScheduleController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'next_due_date', 'next_due_km', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = MaintenanceSchedule::query();
        $result = $this->indexQuery($request, $query, ['task_name'], ['vehicle_id' => 'vehicle_id', 'interval_type' => 'interval_type']);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreMaintenanceScheduleRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (($data['interval_type'] ?? null) === 'by_km' && isset($data['last_done_km'], $data['interval_km'])) {
            $data['next_due_km'] = (float) $data['last_done_km'] + (int) $data['interval_km'];
        }
        if (($data['interval_type'] ?? null) === 'by_days' && isset($data['last_done_date'], $data['interval_days'])) {
            $data['next_due_date'] = now()->parse((string) $data['last_done_date'])->addDays((int) $data['interval_days'])->toDateString();
        }

        $schedule = MaintenanceSchedule::query()->create($data);

        return $this->successResponse($schedule, 'Maintenance schedule created successfully', 201);
    }

    public function show(string $maintenanceSchedule): JsonResponse
    {
        $model = MaintenanceSchedule::query()->find($maintenanceSchedule);
        if ($model === null) {
            return $this->notFoundResponse('Maintenance schedule not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateMaintenanceScheduleRequest $request, string $maintenanceSchedule): JsonResponse
    {
        $model = MaintenanceSchedule::query()->find($maintenanceSchedule);
        if ($model === null) {
            return $this->notFoundResponse('Maintenance schedule not found');
        }

        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Maintenance schedule updated successfully');
    }

    public function destroy(string $maintenanceSchedule): JsonResponse
    {
        $model = MaintenanceSchedule::query()->find($maintenanceSchedule);
        if ($model === null) {
            return $this->notFoundResponse('Maintenance schedule not found');
        }

        $model->delete();

        return $this->successResponse(null, 'Maintenance schedule deleted successfully');
    }
}
