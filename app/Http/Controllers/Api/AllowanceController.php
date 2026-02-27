<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Allowance\StoreAllowanceRequest;
use App\Http\Requests\Allowance\UpdateAllowanceRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Allowance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllowanceController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'default_amount', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Allowance::query();
        $result = $this->indexQuery($request, $query, ['code', 'name'], []);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreAllowanceRequest $request): JsonResponse
    {
        $allowance = Allowance::create($request->validated());

        return $this->successResponse($allowance, 'Allowance created successfully', 201);
    }

    public function show(string $allowance): JsonResponse
    {
        $model = Allowance::find($allowance);
        if (! $model) {
            return $this->notFoundResponse('Allowance not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateAllowanceRequest $request, string $allowance): JsonResponse
    {
        $model = Allowance::find($allowance);
        if (! $model) {
            return $this->notFoundResponse('Allowance not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Allowance updated successfully');
    }

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
