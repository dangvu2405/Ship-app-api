<?php

namespace App\Http\Controllers\Api;

use App\Http\Traits\HasIndexQuery;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Permissions", description="Quản lý quyền")
 */
class PermissionController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/permissions",
     *     tags={"Permissions"},
     *     summary="Danh sách quyền",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code, name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();
        $result = $this->indexQuery($request, $query, ['code', 'name'], []);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Get(
     *     path="/api/permissions/{id}",
     *     tags={"Permissions"},
     *     summary="Chi tiết quyền",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $permission): JsonResponse
    {
        $model = Permission::find($permission);
        if (! $model) {
            return $this->notFoundResponse('Permission not found');
        }

        return $this->successResponse($model);
    }
}
