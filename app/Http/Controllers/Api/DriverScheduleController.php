<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Schedule\ApproveScheduleRequest;
use App\Http\Requests\Schedule\IndexScheduleRequest;
use App\Http\Requests\Schedule\OverrideScheduleRequest;
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
final class DriverScheduleController extends BaseController
{
    public function __construct(private readonly ScheduleService $schedule_service) {}

    /**
     * @OA\Get(
     *     path="/api/v1/driver-schedules",
     *     tags={"Driver Schedules"},
     *     summary="Danh sách lịch làm việc",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="driver_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="office_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="work_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"draft","submitted","approved","locked"})),
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(IndexScheduleRequest $request): JsonResponse
    {
        $schedules = $this->schedule_service->paginateSchedulesForIndex($request->validated());

        return $this->successResponse($schedules, 'Schedules retrieved.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/driver-schedules",
     *     tags={"Driver Schedules"},
     *     summary="Tạo lịch làm việc mới",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"driver_id","office_id","work_date","start_time","end_time"},
     *
     *         @OA\Property(property="driver_id", type="integer"),
     *         @OA\Property(property="office_id", type="integer"),
     *         @OA\Property(property="work_date", type="string", format="date"),
     *         @OA\Property(property="shift_code", type="string", enum={"day","night","split","custom"}),
     *         @OA\Property(property="start_time", type="string", example="07:00"),
     *         @OA\Property(property="end_time", type="string", example="17:00"),
     *         @OA\Property(property="vehicle_id", type="integer", nullable=true),
     *         @OA\Property(property="notes", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=409, description="Xung đột lịch")
     * )
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        try {
            $schedule = $this->schedule_service->create($request->validated(), $request->user());

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
            $schedule = $this->schedule_service->update($driverWorkSchedule, $request->validated());

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
            $schedule = $this->schedule_service->submit($driverWorkSchedule, $request->user());

            return $this->successResponse($schedule, 'Schedule submitted for approval.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function approve(ApproveScheduleRequest $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        $validated = $request->validated();

        try {
            $schedule = $this->schedule_service->approve(
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
            $schedule = $this->schedule_service->reject($driverWorkSchedule, $request->user());

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
        try {
            $schedule = $this->schedule_service->lockSingleRow($driverWorkSchedule, $request->user());

            return $this->successResponse($schedule, 'Schedule locked.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Manager override for locked/approved schedules.
     */
    public function override(OverrideScheduleRequest $request, DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        try {
            $payload = $request->validated();
            $override_reason = (string) ($payload['override_reason'] ?? '');
            unset($payload['override_reason']);

            $schedule = $this->schedule_service->managerOverrideSchedule(
                $driverWorkSchedule,
                $payload,
                $override_reason,
            );

            return $this->successResponse($schedule, 'Schedule overridden successfully.');
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Hours-of-service pre-check for a schedule row.
     */
    public function hosCheck(DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        $summary = $this->schedule_service->dailyHoursSummaryForRow($driverWorkSchedule);
        $message = $summary['is_ok'] ? 'HOS check passed.' : 'HOS check failed.';

        return $this->successResponse($summary, $message);
    }

    public function destroy(DriverWorkSchedule $driverWorkSchedule): JsonResponse
    {
        try {
            $this->schedule_service->destroyIfAllowed($driverWorkSchedule);

            return $this->successResponse(null, 'Schedule deleted.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }
}
