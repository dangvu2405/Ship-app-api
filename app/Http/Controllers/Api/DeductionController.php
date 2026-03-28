<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Deduction\StoreDeductionRequest;
use App\Http\Requests\Deduction\UpdateDeductionRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Deduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Deductions", description="Quản lý khoản khấu trừ")
 */
class DeductionController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/deductions",
     *     tags={"Deductions"},
     *     summary="Danh sách khoản khấu trừ",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code, name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Deduction::query();
        $result = $this->indexQuery($request, $query, ['code', 'name'], []);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/deductions",
     *     tags={"Deductions"},
     *     summary="Tạo khoản khấu trừ mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","name"},
     *             @OA\Property(property="code", type="string", example="DED001"),
     *             @OA\Property(property="name", type="string", example="Bảo hiểm xã hội"),
     *             @OA\Property(property="default_amount", type="number"),
     *             @OA\Property(property="percentage", type="number"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreDeductionRequest $request): JsonResponse
    {
        $deduction = Deduction::create($request->validated());

        return $this->successResponse($deduction, 'Deduction created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/deductions/{id}",
     *     tags={"Deductions"},
     *     summary="Chi tiết khoản khấu trừ",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $deduction): JsonResponse
    {
        $model = Deduction::find($deduction);
        if (! $model) {
            return $this->notFoundResponse('Deduction not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/deductions/{id}",
     *     tags={"Deductions"},
     *     summary="Cập nhật khoản khấu trừ",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="default_amount", type="number"),
     *             @OA\Property(property="percentage", type="number"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateDeductionRequest $request, string $deduction): JsonResponse
    {
        $model = Deduction::find($deduction);
        if (! $model) {
            return $this->notFoundResponse('Deduction not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Deduction updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/deductions/{id}",
     *     tags={"Deductions"},
     *     summary="Xóa khoản khấu trừ",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $deduction): JsonResponse
    {
        $model = Deduction::find($deduction);
        if (! $model) {
            return $this->notFoundResponse('Deduction not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Deduction deleted successfully');
    }
}
