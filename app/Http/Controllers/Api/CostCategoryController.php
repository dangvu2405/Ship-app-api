<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\CostCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\CostCategoryResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class CostCategoryController extends BaseController
{
    public function __construct()
    {
        $this->authorizeResource(CostCategory::class, 'cost_category');
    }

    public function index(Request $request): JsonResource
    {
        $costCategories = CostCategory::query()
            ->where('company_id', app(\App\Tenancy\TenantContext::class)->getCompanyId())
            ->paginate(15);

        return CostCategoryResource::collection($costCategories);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:cost_categories',
            'name' => 'required|string|max:255',
            'requires_receipt' => 'nullable|boolean',
            'approval_threshold' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $costCategory = DB::transaction(function () use ($validated) {
            $costCategory = CostCategory::create(array_merge($validated, [
                'company_id' => app(\App\Tenancy\TenantContext::class)->getCompanyId() ?? 1,
            ]));
            return $costCategory;
        });

        return (new CostCategoryResource($costCategory))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CostCategory $costCategory): JsonResource
    {
        return new CostCategoryResource($costCategory);
    }

    public function update(Request $request, CostCategory $costCategory): JsonResource
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:cost_categories,code,' . $costCategory->id,
            'name' => 'sometimes|required|string|max:255',
            'requires_receipt' => 'sometimes|nullable|boolean',
            'approval_threshold' => 'sometimes|nullable|numeric|min:0',
            'sort_order' => 'sometimes|nullable|integer',
            'is_active' => 'sometimes|nullable|boolean',
        ]);

        DB::transaction(function () use ($validated, $costCategory) {
            $costCategory->update($validated);
        });

        return new CostCategoryResource($costCategory);
    }

    public function destroy(CostCategory $costCategory): JsonResponse
    {
        DB::transaction(function () use ($costCategory) {
            $costCategory->delete();
        });

        return response()->json(null, 204);
    }
}
