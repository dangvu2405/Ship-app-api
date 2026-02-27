<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Role::query()->with('permissions');
        $result = $this->indexQuery($request, $query, ['name'], []);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create($request->validated());

        return $this->successResponse($role->load('permissions'), 'Role created successfully', 201);
    }

    public function show(string $role): JsonResponse
    {
        $model = Role::with('permissions')->find($role);
        if (! $model) {
            return $this->notFoundResponse('Role not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateRoleRequest $request, string $role): JsonResponse
    {
        $model = Role::find($role);
        if (! $model) {
            return $this->notFoundResponse('Role not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh('permissions'), 'Role updated successfully');
    }

    public function destroy(string $role): JsonResponse
    {
        $model = Role::find($role);
        if (! $model) {
            return $this->notFoundResponse('Role not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Role deleted successfully');
    }

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
