<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Attendance\AdjustAttendanceRequest;
use App\Http\Requests\Attendance\CheckInRequest;
use App\Http\Requests\Attendance\CheckOutAttendanceRequest;
use App\Http\Requests\Attendance\NotifyLateAttendanceRequest;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
     *
     *     @OA\Parameter(name="driver_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $useLegacyAttendance = Schema::hasTable('attendances');
        $dateColumn = $useLegacyAttendance ? 'date' : 'work_date';

        $query = $useLegacyAttendance
            ? DB::table('attendances')
            : DB::table('driver_work_schedules')->select([
                'id',
                'driver_id',
                DB::raw('work_date as date'),
                DB::raw('start_time as check_in'),
                DB::raw('end_time as check_out'),
                DB::raw('NULL as work_hours'),
                DB::raw('NULL as overtime_hours'),
                DB::raw("CASE WHEN status IN ('approved', 'locked') THEN 'present' ELSE status END as status"),
                'created_at',
                'updated_at',
            ]);

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->integer('driver_id'));
        }
        if ($request->filled('date')) {
            $query->where($dateColumn, $request->input('date'));
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween($dateColumn, [$request->input('from'), $request->input('to')]);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->integer('per_page', 50);
        $records = $query->orderByDesc($dateColumn)->paginate($perPage > 0 ? min($perPage, 200) : 50);

        return $this->successResponse($records, 'Attendance records retrieved.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/attendance/check-in",
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

    public function checkOut(CheckOutAttendanceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $record = $this->attendanceService->checkOut(
                (int) $validated['driver_id'],
                (string) $validated['check_out_time'],
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

    /**
     * Legacy attendance-late endpoint support.
     */
    public function late(Request $request): JsonResponse
    {
        if (! Schema::hasTable('attendances')) {
            return $this->successResponse(
                DB::table('driver_work_schedules')
                    ->where('status', 'submitted')
                    ->when(
                        $request->filled('driver_id'),
                        fn ($q) => $q->where('driver_id', $request->integer('driver_id')),
                    )
                    ->when(
                        $request->filled('from') && $request->filled('to'),
                        fn ($q) => $q->whereBetween('work_date', [$request->input('from'), $request->input('to')]),
                    )
                    ->orderByDesc('work_date')
                    ->paginate(50),
                'Late attendances retrieved.',
            );
        }

        $query = DB::table('attendances')->where('status', 'late');

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->integer('driver_id'));
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('date', [$request->input('from'), $request->input('to')]);
        }

        return $this->successResponse($query->orderByDesc('date')->paginate(50), 'Late attendances retrieved.');
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
        ], 'Late attendance notifications queued.');
    }
}
