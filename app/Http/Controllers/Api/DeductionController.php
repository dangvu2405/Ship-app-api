<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Deduction\StoreDeductionRequest;
use App\Http\Requests\Deduction\UpdateDeductionRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Deduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeductionController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Deduction::query();
        $result = $this->indexQuery($request, $query, ['code', 'name'], []);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreDeductionRequest $request): JsonResponse
    {
        $deduction = Deduction::create($request->validated());

        return $this->successResponse($deduction, 'Deduction created successfully', 201);
    }

    public function show(string $deduction): JsonResponse
    {
        $model = Deduction::find($deduction);
        if (! $model) {
            return $this->notFoundResponse('Deduction not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateDeductionRequest $request, string $deduction): JsonResponse
    {
        $model = Deduction::find($deduction);
        if (! $model) {
            return $this->notFoundResponse('Deduction not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Deduction updated successfully');
    }

    public function destroy(string $deduction): JsonResponse
    {
        $model = Deduction::find($deduction);
        if (! $model) {
            return $this->notFoundResponse('Deduction not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Deduction deleted successfully');
    }
}
