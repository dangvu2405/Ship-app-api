<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Office\StoreOfficeRequest;
use App\Http\Requests\Office\UpdateOfficeRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Office;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Offices", description="Quản lý văn phòng/chi nhánh")
 */
class OfficeController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'company_id', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/offices",
     *     tags={"Offices"},
     *     summary="Danh sách văn phòng",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code, name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="company_id", in="query", description="Lọc theo công ty", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Office::query()->with('company');
        $result = $this->indexQuery($request, $query, ['code', 'name'], ['company_id' => 'company_id']);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/offices",
     *     tags={"Offices"},
     *     summary="Tạo văn phòng mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","name","company_id"},
     *             @OA\Property(property="code", type="string", example="OFF001"),
     *             @OA\Property(property="name", type="string", example="Văn phòng HN"),
     *             @OA\Property(property="company_id", type="integer", example=1),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="manager_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreOfficeRequest $request): JsonResponse
    {
        $office = Office::create($request->validated());

        return $this->successResponse($office->load('company'), 'Office created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/offices/{id}",
     *     tags={"Offices"},
     *     summary="Chi tiết văn phòng",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $office): JsonResponse
    {
        $model = Office::with('company', 'manager')->find($office);
        if (! $model) {
            return $this->notFoundResponse('Office not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/offices/{id}",
     *     tags={"Offices"},
     *     summary="Cập nhật văn phòng",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="company_id", type="integer"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="manager_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateOfficeRequest $request, string $office): JsonResponse
    {
        $model = Office::find($office);
        if (! $model) {
            return $this->notFoundResponse('Office not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['company', 'manager']), 'Office updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/offices/{id}",
     *     tags={"Offices"},
     *     summary="Xóa văn phòng",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $office): JsonResponse
    {
        $model = Office::find($office);
        if (! $model) {
            return $this->notFoundResponse('Office not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Office deleted successfully');
    }
}
