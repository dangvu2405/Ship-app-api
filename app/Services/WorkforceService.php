<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DriverWorkSchedule;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class WorkforceService
{
    public function __construct(
        private readonly ScheduleService $schedule_service,
    ) {}

    /**
     * @param  array<string, mixed>  $validated  from WorkforceSchedulesIndexRequest
     */
    public function paginateSchedulesForWorkforce(Request $request, array $validated): LengthAwarePaginator
    {
        $query = DriverWorkSchedule::query()
            ->with(['driver.user', 'vehicle', 'office'])
            ->whereBetween('work_date', [(string) $validated['from'], (string) $validated['to']]);

        $company_id = $this->resolveCompanyId($request);
        if ($company_id !== null && Schema::hasColumn('driver_work_schedules', 'company_id')) {
            $query->where('company_id', $company_id);
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

        $per_page = (int) ($validated['per_page'] ?? 200);

        return $query->orderBy('work_date')->orderBy('start_time')->paginate($per_page);
    }

    public function findScheduleForWorkforceMutation(Request $request, int $id): DriverWorkSchedule
    {
        return DriverWorkSchedule::query()
            ->when(
                $this->resolveCompanyId($request) !== null && Schema::hasColumn('driver_work_schedules', 'company_id'),
                fn ($q) => $q->where('company_id', $this->resolveCompanyId($request)),
            )
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $validated  WorkforceApproveScheduleRequest
     */
    public function approveScheduleForWorkforce(
        Request $request,
        int $id,
        array $validated,
    ): DriverWorkSchedule {
        $schedule = $this->findScheduleForWorkforceMutation($request, $id);

        return $this->schedule_service->approve(
            $schedule,
            $request->user(),
            (bool) ($validated['hos_override'] ?? false),
            (string) ($validated['override_reason'] ?? ''),
        );
    }

    public function lockApprovedScheduleForWorkforce(Request $request, int $id): DriverWorkSchedule
    {
        $schedule = $this->findScheduleForWorkforceMutation($request, $id);

        if ($schedule->status !== 'approved') {
            throw new InvalidArgumentException('Chỉ khóa được lịch đã approved', 422);
        }

        $schedule->update([
            'status' => 'locked',
            'locked_by' => $request->user()->id,
            'locked_at' => now(),
        ]);

        return $schedule->fresh();
    }

    /**
     * @param  array<string, mixed>  $validated  WorkforceLeaveRequestsIndexRequest
     */
    public function paginateLeaveRequestsForWorkforce(Request $request, array $validated): LengthAwarePaginator
    {
        $company_id = $this->resolveCompanyId($request);

        $query = LeaveRequest::query()
            ->with(['leaveType', 'driver'])
            ->where(function ($q) use ($validated): void {
                $q->where('from_date', '<=', $validated['to'])
                    ->where('to_date', '>=', $validated['from']);
            });

        if ($company_id !== null) {
            $query->whereHas('driver', fn ($driver_query) => $driver_query->where('company_id', $company_id));
        }

        if (isset($validated['driver_id'])) {
            $query->where('driver_id', (int) $validated['driver_id']);
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $per_page = (int) ($validated['per_page'] ?? 200);

        return $query->orderBy('from_date')->paginate($per_page);
    }

    /**
     * @param  array<string, mixed>  $validated  WorkforceAbsencesIndexRequest
     * @return array{current_page: int, last_page: int, per_page: int, total: int, data: \Illuminate\Support\Collection<int, array<string, mixed>>}
     */
    public function absencesPayloadForWorkforce(Request $request, array $validated): array
    {
        $company_id = $this->resolveCompanyId($request);

        $query = DriverWorkSchedule::query()
            ->whereBetween('work_date', [(string) $validated['from'], (string) $validated['to']])
            ->where('status', 'locked')
            ->when(
                $company_id !== null && Schema::hasColumn('driver_work_schedules', 'company_id'),
                fn ($q) => $q->where('company_id', $company_id),
            )
            ->when(
                isset($validated['driver_id']),
                fn ($q) => $q->where('driver_id', (int) $validated['driver_id']),
            )
            ->select(['id', 'driver_id', 'work_date'])
            ->orderBy('work_date');

        if (Schema::hasTable('leave_requests')) {
            $query->whereNotExists(function ($sub_query): void {
                $sub_query->select(DB::raw(1))
                    ->from('leave_requests')
                    ->whereColumn('leave_requests.driver_id', 'driver_work_schedules.driver_id')
                    ->whereColumn('leave_requests.from_date', '<=', 'driver_work_schedules.work_date')
                    ->whereColumn('leave_requests.to_date', '>=', 'driver_work_schedules.work_date')
                    ->where('leave_requests.status', 'approved');
            });
        }

        $per_page = (int) ($validated['per_page'] ?? 200);
        $data = $query->paginate($per_page);

        return [
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
        ];
    }

    private function resolveCompanyId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return null;
        }

        $company_id = $user->driver?->company_id;

        return $company_id !== null ? (int) $company_id : null;
    }
}
