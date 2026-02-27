<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'office_id', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Department::query()->with('office');
        $result = $this->indexQuery($request, $query, ['code', 'name'], ['office_id' => 'office_id']);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return $this->successResponse($department->load('office'), 'Department created successfully', 201);
    }

    public function show(string $department): JsonResponse
    {
        $model = Department::with('office', 'parent')->find($department);
        if (! $model) {
            return $this->notFoundResponse('Department not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateDepartmentRequest $request, string $department): JsonResponse
    {
        $model = Department::find($department);
        if (! $model) {
            return $this->notFoundResponse('Department not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['office', 'parent']), 'Department updated successfully');
    }

    public function destroy(string $department): JsonResponse
    {
        $model = Department::find($department);
        if (! $model) {
            return $this->notFoundResponse('Department not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Department deleted successfully');
    }
}
