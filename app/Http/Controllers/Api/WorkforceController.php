<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\DriverScheduleResource;
use App\Http\Resources\LeaveRequestResource;
use App\Models\DriverWorkSchedule;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class WorkforceController extends BaseController
{
    public function schedules(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $validated = $request->validate([
            'from'       => ['required', 'date'],
            'to'         => ['required', 'date', 'after_or_equal:from'],
            'driver_id'  => ['nullable', 'integer', 'exists:drivers,id'],
            'office_id'  => ['nullable', 'integer', 'exists:offices,id'],
            'shift_code' => ['nullable', 'string', 'max:20'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $query = DriverWorkSchedule::query()
            ->with(['driver.user', 'vehicle', 'office'])
            ->whereBetween('work_date', [$validated['from'], $validated['to']]);

        $companyId = $this->resolveCompanyId($request);
        if ($companyId !== null && Schema::hasColumn('driver_work_schedules', 'company_id')) {
            $query->where('company_id', $companyId);
        }

        if (isset($validated['driver_id'])) {
            $query->where('driver_id', (int) $validated['driver_id']);
        }
        if (isset($validated['office_id'])) {
            $query->where('office_id', (int) $validated['office_id']);
        }
        if (isset($validated['shift_code'])) {
            $query->where('shift_code', $validated['shift_code']);
        }

        $perPage = (int) ($validated['per_page'] ?? 200);
        $data = $query
            ->orderBy('work_date')
            ->orderBy('start_time')
            ->paginate($perPage);

        return DriverScheduleResource::collection($data);
    }

    public function approveSchedule(Request $request, int $id): JsonResponse
    {
        $schedule = DriverWorkSchedule::query()
            ->when(
                $this->resolveCompanyId($request) !== null && Schema::hasColumn('driver_work_schedules', 'company_id'),
                fn ($q) => $q->where('company_id', $this->resolveCompanyId($request)),
            )
            ->findOrFail($id);

        if ($schedule->status === 'locked') {
            return $this->errorResponse('Lịch đã khóa, không thể thay đổi', 422);
        }

        $schedule->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return $this->successResponse(new DriverScheduleResource($schedule->loadMissing('vehicle')));
    }

    public function lockSchedule(Request $request, int $id): JsonResponse
    {
        $schedule = DriverWorkSchedule::query()
            ->when(
                $this->resolveCompanyId($request) !== null && Schema::hasColumn('driver_work_schedules', 'company_id'),
                fn ($q) => $q->where('company_id', $this->resolveCompanyId($request)),
            )
            ->findOrFail($id);

        if ($schedule->status !== 'approved') {
            return $this->errorResponse('Chỉ khóa được lịch đã approved', 422);
        }

        $schedule->update([
            'status'    => 'locked',
            'locked_by' => $request->user()->id,
            'locked_at' => now(),
        ]);

        return $this->successResponse(new DriverScheduleResource($schedule->loadMissing('vehicle')));
    }

    public function leaveRequests(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $validated = $request->validate([
            'from'      => ['required', 'date'],
            'to'        => ['required', 'date', 'after_or_equal:from'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'status'    => ['nullable', 'in:pending,approved,rejected,cancelled'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $companyId = $this->resolveCompanyId($request);

        $query = LeaveRequest::query()
            ->with(['leaveType', 'driver'])
            ->where(function ($q) use ($validated): void {
                $q->where('from_date', '<=', $validated['to'])
                    ->where('to_date', '>=', $validated['from']);
            });

        if ($companyId !== null) {
            $query->whereHas('driver', fn ($driverQuery) => $driverQuery->where('company_id', $companyId));
        }

        if (isset($validated['driver_id'])) {
            $query->where('driver_id', (int) $validated['driver_id']);
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $perPage = (int) ($validated['per_page'] ?? 200);
        $data = $query->orderBy('from_date')->paginate($perPage);

        return LeaveRequestResource::collection($data);
    }

    public function absences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from'      => ['required', 'date'],
            'to'        => ['required', 'date', 'after_or_equal:from'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $companyId = $this->resolveCompanyId($request);

        $query = DriverWorkSchedule::query()
            ->whereBetween('work_date', [$validated['from'], $validated['to']])
            ->where('status', 'locked')
            ->when(
                $companyId !== null && Schema::hasColumn('driver_work_schedules', 'company_id'),
                fn ($q) => $q->where('company_id', $companyId),
            )
            ->when(
                isset($validated['driver_id']),
                fn ($q) => $q->where('driver_id', (int) $validated['driver_id']),
            )
            ->select(['id', 'driver_id', 'work_date'])
            ->orderBy('work_date');

        if (Schema::hasTable('leave_requests')) {
            $query->whereNotExists(function ($subQuery): void {
                $subQuery->select(DB::raw(1))
                    ->from('leave_requests')
                    ->whereColumn('leave_requests.driver_id', 'driver_work_schedules.driver_id')
                    ->whereColumn('leave_requests.from_date', '<=', 'driver_work_schedules.work_date')
                    ->whereColumn('leave_requests.to_date', '>=', 'driver_work_schedules.work_date')
                    ->where('leave_requests.status', 'approved');
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 200);
        $data = $query->paginate($perPage);

        return $this->successResponse([
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'data' => $data->getCollection()->map(static fn (DriverWorkSchedule $schedule): array => [
                'id' => $schedule->id,
                'driver_id' => $schedule->driver_id,
                'date' => $schedule->work_date?->toDateString(),
                'reason' => null,
            ])->values(),
        ]);
    }

    private function resolveCompanyId(Request $request): ?int
    {
        $companyId = $request->user()?->driver?->company_id;

        return $companyId !== null ? (int) $companyId : null;
    }
}
