<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Schedule\StoreScheduleRequest;
use App\Http\Requests\Schedule\UpdateScheduleRequest;
use App\Models\DriverWorkSchedule;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * @OA\Tag(name="Driver Schedules", description="Quản lý lịch làm việc tài xế")
 */
class DriverScheduleController extends BaseController
{
    public function __construct(private readonly ScheduleService $scheduleService) {}

    /**
     * @OA\Get(
     *     path="/api/v1/driver-schedules",
     *     tags={"Driver Schedules"},
     *     summary="Danh sách lịch làm việc",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="driver_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="office_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="work_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"draft","submitted","approved","locked"})),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = DriverWorkSchedule::query()->with(['driver', 'vehicle', 'office']);

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->integer('driver_id'));
        }
        if ($request->filled('office_id')) {
            $query->where('office_id', $request->integer('office_id'));
        }
        if ($request->filled('work_date')) {
            $query->where('work_date', $request->input('work_date'));
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('work_date', [$request->input('from'), $request->input('to')]);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $schedules = $query->orderBy('work_date')->orderBy('shift_code')->paginate(50);

        return $this->successResponse($schedules, 'Schedules retrieved.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/driver-schedules",
     *     tags={"Driver Schedules"},
     *     summary="Tạo lịch làm việc mới",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"driver_id","office_id","work_date","start_time","end_time"},
     *         @OA\Property(property="driver_id", type="integer"),
     *         @OA\Property(property="office_id", type="integer"),
     *         @OA\Property(property="work_date", type="string", format="date"),
     *         @OA\Property(property="shift_code", type="string", enum={"day","night","split","custom"}),
     *         @OA\Property(property="start_time", type="string", example="07:00"),
     *         @OA\Property(property="end_time", type="string", example="17:00"),
     *         @OA\Property(property="vehicle_id", type="integer", nullable=true),
     *         @OA\Property(property="notes", type="string", nullable=true)
     *     )),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=409, description="Xung đột lịch")
     * )
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->create($request->validated(), $request->user());

            return $this->successResponse($schedule->load(['driver', 'vehicle', 'office']), 'Schedule created.', 201);
        } catch (InvalidArgumentException $e) {
            $code = $e->getCode() === 409 ? 409 : 422;

            return $this->errorResponse($e->getMessage(), $code);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function show(DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        return $this->successResponse(
            $driverWorkSchedule->load(['driver', 'vehicle', 'office', 'approver']),
            'Schedule retrieved.',
        );
    }

    public function update(UpdateScheduleRequest $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->update($driverWorkSchedule, $request->validated());

            return $this->successResponse($schedule->load(['driver', 'vehicle', 'office']), 'Schedule updated.');
        } catch (InvalidArgumentException $e) {
            $code = $e->getCode() === 409 ? 409 : 422;

            return $this->errorResponse($e->getMessage(), $code);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function submit(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->submit($driverWorkSchedule, $request->user());

            return $this->successResponse($schedule, 'Schedule submitted for approval.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function approve(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        $validated = $request->validate([
            'hos_override'    => ['boolean'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $schedule = $this->scheduleService->approve(
                $driverWorkSchedule,
                $request->user(),
                (bool) ($validated['hos_override'] ?? false),
                (string) ($validated['override_reason'] ?? ''),
            );

            return $this->successResponse($schedule, 'Schedule approved.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function reject(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->reject($driverWorkSchedule, $request->user());

            return $this->successResponse($schedule, 'Schedule rejected and returned to draft.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Lock a single schedule row.
     */
    public function lock(Request $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        if (! in_array($driverWorkSchedule->status, ['approved', 'submitted'], true)) {
            return $this->errorResponse('Only submitted/approved schedules can be locked.', 422);
        }

        $driverWorkSchedule->update([
            'status' => 'locked',
            'locked_by' => $request->user()->id,
            'locked_at' => now(),
        ]);

        return $this->successResponse($driverWorkSchedule->fresh(), 'Schedule locked.');
    }

    /**
     * Manager override for locked/approved schedules.
     */
    public function override(UpdateScheduleRequest $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        $request->validate([
            'override_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $payload = $request->validated();
            $overrideReason = $payload['override_reason'] ?? '';
            unset($payload['override_reason']);

            $payload['notes'] = trim(($driverWorkSchedule->notes ?? '').' | OVERRIDE: '.$overrideReason);

            // Allow editing even when locked through explicit override endpoint.
            $driverWorkSchedule->fill($payload);
            $driverWorkSchedule->status = 'approved';
            $driverWorkSchedule->locked_by = null;
            $driverWorkSchedule->locked_at = null;
            $driverWorkSchedule->save();

            return $this->successResponse(
                $driverWorkSchedule->fresh(['driver', 'vehicle', 'office']),
                'Schedule overridden successfully.',
            );
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Hours-of-service pre-check for a schedule row.
     */
    public function hosCheck(DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        $driverId = (int) $driverWorkSchedule->driver_id;
        $date = $driverWorkSchedule->work_date->toDateString();

        $hours = DriverWorkSchedule::query()
            ->where('driver_id', $driverId)
            ->where('work_date', $date)
            ->whereNotIn('status', ['draft'])
            ->get()
            ->sum(static function (DriverWorkSchedule $row): float {
                $start = strtotime($row->start_time);
                $end = strtotime($row->end_time);
                if ($end < $start) {
                    $end += 86400;
                }

                return ($end - $start) / 3600;
            });

        $limitHours = 12.0;
        $ok = $hours <= $limitHours;

        return $this->successResponse([
            'driver_id' => $driverId,
            'work_date' => $date,
            'total_hours' => round($hours, 2),
            'limit_hours' => $limitHours,
            'is_ok' => $ok,
        ], $ok ? 'HOS check passed.' : 'HOS check failed.');
    }

    public function destroy(DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        if ($driverWorkSchedule->isLocked()) {
            return $this->errorResponse('Cannot delete a locked schedule.', 422);
        }
        $driverWorkSchedule->delete();

        return $this->successResponse(null, 'Schedule deleted.');
    }
}
