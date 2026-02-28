<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Allowance\StoreAllowanceRequest;
use App\Http\Requests\Allowance\UpdateAllowanceRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Allowance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Allowances", description="Quản lý phụ cấp")
 */
class AllowanceController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'default_amount', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/allowances",
     *     tags={"Allowances"},
     *     summary="Danh sách phụ cấp",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code, name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Allowance::query();
        $result = $this->indexQuery($request, $query, ['code', 'name'], []);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/allowances",
     *     tags={"Allowances"},
     *     summary="Tạo phụ cấp mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","name"},
     *             @OA\Property(property="code", type="string", example="ALW001"),
     *             @OA\Property(property="name", type="string", example="Phụ cấp ăn trưa"),
     *             @OA\Property(property="default_amount", type="number", example=500000),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="is_taxable", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreAllowanceRequest $request): JsonResponse
    {
        $allowance = Allowance::create($request->validated());

        return $this->successResponse($allowance, 'Allowance created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/allowances/{id}",
     *     tags={"Allowances"},
     *     summary="Chi tiết phụ cấp",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $allowance): JsonResponse
    {
        $model = Allowance::find($allowance);
        if (! $model) {
            return $this->notFoundResponse('Allowance not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/allowances/{id}",
     *     tags={"Allowances"},
     *     summary="Cập nhật phụ cấp",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="default_amount", type="number"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="is_taxable", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateAllowanceRequest $request, string $allowance): JsonResponse
    {
        $model = Allowance::find($allowance);
        if (! $model) {
            return $this->notFoundResponse('Allowance not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Allowance updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/allowances/{id}",
     *     tags={"Allowances"},
     *     summary="Xóa phụ cấp",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $allowance): JsonResponse
    {
        $model = Allowance::find($allowance);
        if (! $model) {
            return $this->notFoundResponse('Allowance not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Allowance deleted successfully');
    }
}
