<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Roles", description="Quản lý vai trò")
 */
class RoleController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/roles",
     *     tags={"Roles"},
     *     summary="Danh sách vai trò",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo tên", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query()->with('permissions');
        $result = $this->indexQuery($request, $query, ['name'], []);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/roles",
     *     tags={"Roles"},
     *     summary="Tạo vai trò mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="admin"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create($request->validated());

        return $this->successResponse($role->load('permissions'), 'Role created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Chi tiết vai trò",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $role): JsonResponse
    {
        $model = Role::with('permissions')->find($role);
        if (! $model) {
            return $this->notFoundResponse('Role not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Cập nhật vai trò",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateRoleRequest $request, string $role): JsonResponse
    {
        $model = Role::find($role);
        if (! $model) {
            return $this->notFoundResponse('Role not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh('permissions'), 'Role updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Xóa vai trò",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $role): JsonResponse
    {
        $model = Role::find($role);
        if (! $model) {
            return $this->notFoundResponse('Role not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Role deleted successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/roles/{id}/sync-permissions",
     *     tags={"Roles"},
     *     summary="Đồng bộ quyền cho vai trò",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="permission_ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Đồng bộ thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function syncPermissions(Request $request, string $role): JsonResponse
    {
        $model = Role::find($role);
        if (! $model) {
            return $this->notFoundResponse('Role not found');
        }
        $request->validate(['permission_ids' => 'array', 'permission_ids.*' => 'exists:permissions,id']);
        $model->permissions()->sync($request->input('permission_ids', []));

        return $this->successResponse($model->fresh('permissions'), 'Permissions synced successfully');
    }
}
