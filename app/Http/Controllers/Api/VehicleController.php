<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Vehicles", description="Quản lý phương tiện")
 */
class VehicleController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'plate_number', 'type', 'status', 'office_id', 'year', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/vehicles",
     *     tags={"Vehicles"},
     *     summary="Danh sách phương tiện",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo plate_number, brand, model", @OA\Schema(type="string")),
     *     @OA\Parameter(name="office_id", in="query", description="Lọc theo văn phòng", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::query()->with('office');
        $result = $this->indexQuery($request, $query, ['plate_number', 'brand', 'model'], [
            'office_id' => 'office_id',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'api.common.ok');
    }

    /**
     * @OA\Post(
     *     path="/api/vehicles",
     *     tags={"Vehicles"},
     *     summary="Tạo phương tiện mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plate_number","office_id"},
     *             @OA\Property(property="plate_number", type="string", example="51A-12345"),
     *             @OA\Property(property="office_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", example="truck"),
     *             @OA\Property(property="brand", type="string", example="Toyota"),
     *             @OA\Property(property="model", type="string", example="Hilux"),
     *             @OA\Property(property="year", type="integer", example=2022),
     *             @OA\Property(property="capacity", type="number"),
     *             @OA\Property(property="status", type="string", enum={"active","maintenance","inactive"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = Vehicle::create($request->validated());

        return $this->successResponse($vehicle->load('office'), 'api.vehicle.created', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/vehicles/{id}",
     *     tags={"Vehicles"},
     *     summary="Chi tiết phương tiện",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $vehicle): JsonResponse
    {
        $model = Vehicle::with('office')->find($vehicle);
        if (! $model) {
            return $this->notFoundResponse('api.vehicle.not_found');
        }

        return $this->successResponse($model, 'api.common.ok');
    }

    /**
     * @OA\Put(
     *     path="/api/vehicles/{id}",
     *     tags={"Vehicles"},
     *     summary="Cập nhật phương tiện",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="plate_number", type="string"),
     *             @OA\Property(property="office_id", type="integer"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="brand", type="string"),
     *             @OA\Property(property="model", type="string"),
     *             @OA\Property(property="year", type="integer"),
     *             @OA\Property(property="capacity", type="number"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateVehicleRequest $request, string $vehicle): JsonResponse
    {
        $model = Vehicle::find($vehicle);
        if (! $model) {
            return $this->notFoundResponse('api.vehicle.not_found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh('office'), 'api.vehicle.updated');
    }

    /**
     * @OA\Delete(
     *     path="/api/vehicles/{id}",
     *     tags={"Vehicles"},
     *     summary="Xóa phương tiện",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Không thể xóa do đang được gán")
     * )
     */
    public function destroy(string $vehicle): JsonResponse
    {
        $model = Vehicle::find($vehicle);
        if (! $model) {
            return $this->notFoundResponse('api.vehicle.not_found');
        }
        if (VehicleAssignment::where('vehicle_id', $vehicle)->exists()) {
            return $this->errorResponse('api.vehicle.cannot_delete_assigned', 422);
        }
        $model->delete();

        return $this->successResponse(null, 'api.vehicle.deleted');
    }
}
