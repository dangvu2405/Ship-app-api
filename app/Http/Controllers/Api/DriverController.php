<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Driver\StoreDriverRequest;
use App\Http\Requests\Driver\UpdateDriverRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Drivers", description="Quản lý tài xế")
 */
class DriverController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'license_no', 'available_status', 'expired_date', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/drivers",
     *     tags={"Drivers"},
     *     summary="Danh sách tài xế",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo license_no", @OA\Schema(type="string")),
     *     @OA\Parameter(name="employee_id", in="query", description="Lọc theo nhân viên", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="available_status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Driver::query()->with(['office', 'department', 'position']);
        $result = $this->indexQuery($request, $query, ['license_no'], [
            'office_id' => 'office_id',
            'available_status' => 'available_status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/drivers",
     *     tags={"Drivers"},
     *     summary="Tạo tài xế mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id","license_no","license_class"},
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="license_no", type="string", example="B123456"),
     *             @OA\Property(property="license_class", type="string", example="B2"),
     *             @OA\Property(property="expired_date", type="string", format="date"),
     *             @OA\Property(property="available_status", type="string", enum={"available","on_trip","off"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreDriverRequest $request): JsonResponse
    {
        $driver = Driver::create($request->validated());

        return $this->successResponse($driver->load(['office', 'department', 'position']), 'Driver created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/drivers/{id}",
     *     tags={"Drivers"},
     *     summary="Chi tiết tài xế",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $driver): JsonResponse
    {
        $model = Driver::with(['office', 'department', 'position'])->find($driver);
        if (! $model) {
            return $this->notFoundResponse('Driver not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/drivers/{id}",
     *     tags={"Drivers"},
     *     summary="Cập nhật tài xế",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="license_no", type="string"),
     *             @OA\Property(property="license_class", type="string"),
     *             @OA\Property(property="expired_date", type="string", format="date"),
     *             @OA\Property(property="available_status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateDriverRequest $request, string $driver): JsonResponse
    {
        $model = Driver::find($driver);
        if (! $model) {
            return $this->notFoundResponse('Driver not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['office', 'department', 'position']), 'Driver updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/drivers/{id}",
     *     tags={"Drivers"},
     *     summary="Xóa tài xế",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $driver): JsonResponse
    {
        $model = Driver::find($driver);
        if (! $model) {
            return $this->notFoundResponse('Driver not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Driver deleted successfully');
    }
}
