<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Attendances", description="Quản lý chấm công")
 */
class AttendanceController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'employee_id', 'date', 'status', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/attendances",
     *     tags={"Attendances"},
     *     summary="Danh sách chấm công",
     *     @OA\Parameter(name="employee_id", in="query", description="Lọc theo nhân viên", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::query()->with('employee');
        $result = $this->indexQuery($request, $query, [], [
            'employee_id' => 'employee_id',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/attendances",
     *     tags={"Attendances"},
     *     summary="Tạo bản ghi chấm công mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id","date"},
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="date", type="string", format="date", example="2026-02-28"),
     *             @OA\Property(property="check_in", type="string", format="time", example="08:00:00"),
     *             @OA\Property(property="check_out", type="string", format="time", example="17:00:00"),
     *             @OA\Property(property="status", type="string", enum={"present","absent","late","leave"}, example="present"),
     *             @OA\Property(property="overtime_hours", type="number"),
     *             @OA\Property(property="note", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $attendance = Attendance::create($request->validated());

        return $this->successResponse($attendance->load('employee'), 'Attendance created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/attendances/{id}",
     *     tags={"Attendances"},
     *     summary="Chi tiết chấm công",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $attendance): JsonResponse
    {
        $model = Attendance::with('employee')->find($attendance);
        if (! $model) {
            return $this->notFoundResponse('Attendance not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/attendances/{id}",
     *     tags={"Attendances"},
     *     summary="Cập nhật chấm công",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="check_in", type="string", format="time"),
     *             @OA\Property(property="check_out", type="string", format="time"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="overtime_hours", type="number"),
     *             @OA\Property(property="note", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateAttendanceRequest $request, string $attendance): JsonResponse
    {
        $model = Attendance::find($attendance);
        if (! $model) {
            return $this->notFoundResponse('Attendance not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh('employee'), 'Attendance updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/attendances/{id}",
     *     tags={"Attendances"},
     *     summary="Xóa chấm công",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $attendance): JsonResponse
    {
        $model = Attendance::find($attendance);
        if (! $model) {
            return $this->notFoundResponse('Attendance not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Attendance deleted successfully');
    }
}
