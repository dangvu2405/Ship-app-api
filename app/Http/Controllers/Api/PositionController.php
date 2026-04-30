<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Position\StorePositionRequest;
use App\Http\Requests\Position\UpdatePositionRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Positions", description="Quản lý chức vụ")
 */
class PositionController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'base_salary', 'level', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/positions",
     *     tags={"Positions"},
     *     summary="Danh sách chức vụ",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code, name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Position::query();
        $result = $this->indexQuery($request, $query, ['code', 'name'], []);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/positions",
     *     tags={"Positions"},
     *     summary="Tạo chức vụ mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","name"},
     *             @OA\Property(property="code", type="string", example="POS001"),
     *             @OA\Property(property="name", type="string", example="Trưởng phòng"),
     *             @OA\Property(property="base_salary", type="number", example=15000000),
     *             @OA\Property(property="level", type="integer", example=3),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = Position::create($request->validated());

        return $this->successResponse($position, 'Position created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/positions/{id}",
     *     tags={"Positions"},
     *     summary="Chi tiết chức vụ",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $position): JsonResponse
    {
        $model = Position::find($position);
        if (! $model) {
            return $this->notFoundResponse('Position not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/positions/{id}",
     *     tags={"Positions"},
     *     summary="Cập nhật chức vụ",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="base_salary", type="number"),
     *             @OA\Property(property="level", type="integer"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdatePositionRequest $request, string $position): JsonResponse
    {
        $model = Position::find($position);
        if (! $model) {
            return $this->notFoundResponse('Position not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Position updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/positions/{id}",
     *     tags={"Positions"},
     *     summary="Xóa chức vụ",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $position): JsonResponse
    {
        $model = Position::find($position);
        if (! $model) {
            return $this->notFoundResponse('Position not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Position deleted successfully');
    }
}
