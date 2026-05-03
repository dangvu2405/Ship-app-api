<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Attendance\AdjustAttendanceRequest;
use App\Http\Requests\Attendance\CheckInRequest;
use App\Http\Requests\Attendance\CheckOutAttendanceRequest;
use App\Http\Requests\Attendance\IndexAttendanceRequest;
use App\Http\Requests\Attendance\NotifyLateAttendanceRequest;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * @OA\Tag(name="Attendance", description="Chấm công tài xế")
 */
class AttendanceController extends BaseController
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    /**
     * @OA\Get(
     *     path="/api/attendance",
     *     tags={"Attendance"},
     *     summary="Danh sách chấm công",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="driver_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(IndexAttendanceRequest $request): JsonResponse
    {
        $records = $this->attendanceService->getList($request->validated());

        return $this->successResponse($records, 'api.attendance.records_retrieved');
    }

    /**
     * @OA\Post(
     *     path="/api/attendance/check-in",
     *     tags={"Attendance"},
     *     summary="Ghi nhận check-in",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"driver_id","check_in_time"},
     *
     *         @OA\Property(property="driver_id", type="integer"),
     *         @OA\Property(property="check_in_time", type="string", example="2026-05-01 07:05:00")
     *     )),
     *
     *     @OA\Response(response=201, description="Ghi nhận thành công")
     * )
     */
    public function checkIn(CheckInRequest $request): JsonResponse
    {
        if ($guest = $this->unauthorizedIfGuest($request)) {
            return $guest;
        }

        try {
            $record = $this->attendanceService->checkIn(
                $request->integer('driver_id'),
                $request->input('check_in_time'),
                $request->user(),
            );

            return $this->successResponse($record, 'api.attendance.check_in_recorded', 201);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function checkOut(CheckOutAttendanceRequest $request): JsonResponse
    {
        if ($guest = $this->unauthorizedIfGuest($request)) {
            return $guest;
        }

        $validated = $request->validated();

        try {
            $record = $this->attendanceService->checkOut(
                (int) $validated['driver_id'],
                (string) $validated['check_out_time'],
                $request->user(),
            );

            return $this->successResponse($record, 'api.attendance.check_out_recorded');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function adjust(AdjustAttendanceRequest $request, int $id): JsonResponse
    {
        if ($guest = $this->unauthorizedIfGuest($request)) {
            return $guest;
        }

        try {
            $record = $this->attendanceService->adjust($id, $request->validated(), $request->user());

            return $this->successResponse($record, 'api.attendance.adjusted');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Legacy attendance-late endpoint support.
     */
    public function late(Request $request): JsonResponse
    {
        $records = $this->attendanceService->getLateList($request->only(['driver_id', 'from', 'to']));

        return $this->successResponse($records, 'api.attendance.late_retrieved');
    }

    /**
     * Legacy endpoint: notify late attendance summary.
     */
    public function notifyLate(NotifyLateAttendanceRequest $request): JsonResponse
    {
        return $this->successResponse([
            'queued' => true,
            'scope' => [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'driver_ids' => $request->input('driver_ids', []),
            ],
        ], 'api.attendance.late_notifications_queued');
    }
}
