<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\CostCategory\StoreCostCategoryRequest;
use App\Http\Requests\CostCategory\UpdateCostCategoryRequest;
use App\Http\Resources\CostCategoryResource;
use App\Models\CostCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CostCategoryController extends BaseController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CostCategory::query();

        if ($request->filled('keyword')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->input('keyword') . '%')
                    ->orWhere('code', 'like', '%' . $request->input('keyword') . '%');
            });
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'sort_order');
        $sortOrder = $request->input('sort_order', 'asc');

        $query->orderBy($sortBy, $sortOrder);

        return CostCategoryResource::collection($query->paginate($perPage));
    }

    public function store(StoreCostCategoryRequest $request): CostCategoryResource
    {
        $costCategory = CostCategory::create($request->validated());
        return new CostCategoryResource($costCategory);
    }

    public function update(UpdateCostCategoryRequest $request, CostCategory $costCategory): CostCategoryResource
    {
        $costCategory->update($request->validated());
        return new CostCategoryResource($costCategory->fresh());
    }

    public function destroy(CostCategory $costCategory): Response
    {
        if ($costCategory->tripCosts()->exists()) {
            abort(422, 'Không thể xóa loại chi phí này vì đã có dữ liệu phát sinh trong các chuyến xe.');
        }

        $costCategory->delete();
        return response()->noContent();
    }
}