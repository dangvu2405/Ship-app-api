<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Attendance\AdjustAttendanceRequest;
use App\Http\Requests\Attendance\CheckInRequest;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     *     path="/api/v1/attendance",
     *     tags={"Attendance"},
     *     summary="Danh sách chấm công",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="driver_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('attendances');

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->integer('driver_id'));
        }
        if ($request->filled('date')) {
            $query->where('date', $request->input('date'));
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('date', [$request->input('from'), $request->input('to')]);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $records = $query->orderByDesc('date')->paginate(50);

        return $this->successResponse($records, 'Attendance records retrieved.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/attendance/check-in",
     *     tags={"Attendance"},
     *     summary="Ghi nhận check-in",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"driver_id","check_in_time"},
     *         @OA\Property(property="driver_id", type="integer"),
     *         @OA\Property(property="check_in_time", type="string", example="2026-05-01 07:05:00")
     *     )),
     *     @OA\Response(response=201, description="Ghi nhận thành công")
     * )
     */
    public function checkIn(CheckInRequest $request): JsonResponse
    {
        try {
            $record = $this->attendanceService->checkIn(
                $request->integer('driver_id'),
                $request->input('check_in_time'),
                $request->user(),
            );

            return $this->successResponse($record, 'Check-in recorded.', 201);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function checkOut(Request $request): JsonResponse
    {
        $request->validate([
            'driver_id'      => ['required', 'integer', 'exists:drivers,id'],
            'check_out_time' => ['required', 'date_format:Y-m-d H:i:s'],
        ]);

        try {
            $record = $this->attendanceService->checkOut(
                $request->integer('driver_id'),
                $request->input('check_out_time'),
                $request->user(),
            );

            return $this->successResponse($record, 'Check-out recorded.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function adjust(AdjustAttendanceRequest $request, int $id): JsonResponse
    {
        try {
            $record = $this->attendanceService->adjust($id, $request->validated(), $request->user());

            return $this->successResponse($record, 'Attendance adjusted.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }
}
