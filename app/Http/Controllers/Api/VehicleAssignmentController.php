<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\VehicleAssignment\StoreVehicleAssignmentRequest;
use App\Http\Requests\VehicleAssignment\UpdateVehicleAssignmentRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\VehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Vehicle Assignments", description="Quản lý phân công xe")
 */
class VehicleAssignmentController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'vehicle_id', 'driver_id', 'from_date', 'to_date', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/vehicle_assignments",
     *     tags={"Vehicle Assignments"},
     *     summary="Danh sách phân công xe",
     *
     *     @OA\Parameter(name="vehicle_id", in="query", description="Lọc theo xe", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="driver_id", in="query", description="Lọc theo tài xế", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = VehicleAssignment::query()->with(['vehicle', 'driver']);
        $result = $this->indexQuery($request, $query, [], [
            'vehicle_id' => 'vehicle_id',
            'driver_id' => 'driver_id',
        ]);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/vehicle_assignments",
     *     tags={"Vehicle Assignments"},
     *     summary="Tạo phân công xe mới",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"vehicle_id","driver_id","from_date"},
     *
     *             @OA\Property(property="vehicle_id", type="integer", example=1),
     *             @OA\Property(property="driver_id", type="integer", example=1),
     *             @OA\Property(property="from_date", type="string", format="date"),
     *             @OA\Property(property="to_date", type="string", format="date"),
     *             @OA\Property(property="note", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreVehicleAssignmentRequest $request): JsonResponse
    {
        $assignment = VehicleAssignment::create($request->validated());

        return $this->successResponse($assignment->load(['vehicle', 'driver']), 'Vehicle assignment created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/vehicle_assignments/{id}",
     *     tags={"Vehicle Assignments"},
     *     summary="Chi tiết phân công xe",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $vehicle_assignment): JsonResponse
    {
        $model = VehicleAssignment::with(['vehicle', 'driver'])->find($vehicle_assignment);
        if (! $model) {
            return $this->notFoundResponse('Vehicle assignment not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/vehicle_assignments/{id}",
     *     tags={"Vehicle Assignments"},
     *     summary="Cập nhật phân công xe",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="vehicle_id", type="integer"),
     *             @OA\Property(property="driver_id", type="integer"),
     *             @OA\Property(property="from_date", type="string", format="date"),
     *             @OA\Property(property="to_date", type="string", format="date"),
     *             @OA\Property(property="note", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateVehicleAssignmentRequest $request, string $vehicle_assignment): JsonResponse
    {
        $model = VehicleAssignment::find($vehicle_assignment);
        if (! $model) {
            return $this->notFoundResponse('Vehicle assignment not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['vehicle', 'driver']), 'Vehicle assignment updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/vehicle_assignments/{id}",
     *     tags={"Vehicle Assignments"},
     *     summary="Xóa phân công xe",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $vehicle_assignment): JsonResponse
    {
        $model = VehicleAssignment::find($vehicle_assignment);
        if (! $model) {
            return $this->notFoundResponse('Vehicle assignment not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Vehicle assignment deleted successfully');
    }
}
