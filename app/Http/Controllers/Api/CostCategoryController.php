<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\CostCategory\StoreCostCategoryRequest;
use App\Http\Requests\CostCategory\UpdateCostCategoryRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\CostCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CostCategoryController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'sort_order', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $result = $this->indexQuery($request, CostCategory::query(), ['code', 'name'], []);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreCostCategoryRequest $request): JsonResponse
    {
        $costCategory = CostCategory::query()->create($request->validated());

        return $this->successResponse($costCategory, 'Cost category created successfully', 201);
    }

    public function show(string $costCategory): JsonResponse
    {
        $model = CostCategory::query()->find($costCategory);
        if ($model === null) {
            return $this->notFoundResponse('Cost category not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateCostCategoryRequest $request, string $costCategory): JsonResponse
    {
        $model = CostCategory::query()->find($costCategory);
        if ($model === null) {
            return $this->notFoundResponse('Cost category not found');
        }

        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Cost category updated successfully');
    }

    public function destroy(string $costCategory): JsonResponse
    {
        $model = CostCategory::query()->find($costCategory);
        if ($model === null) {
            return $this->notFoundResponse('Cost category not found');
        }

        $model->delete();

        return $this->successResponse(null, 'Cost category deleted successfully');
    }
}
