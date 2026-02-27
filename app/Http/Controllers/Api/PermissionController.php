<?php

namespace App\Http\Controllers\Api;

use App\Http\Traits\HasIndexQuery;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();
        $result = $this->indexQuery($request, $query, ['code', 'name'], []);

        return $this->successResponse($result, 'OK');
    }

    public function show(string $permission): JsonResponse
    {
        $model = Permission::find($permission);
        if (! $model) {
            return $this->notFoundResponse('Permission not found');
        }

        return $this->successResponse($model);
    }
}
