<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Office\StoreOfficeRequest;
use App\Http\Requests\Office\UpdateOfficeRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Office;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'company_id', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Office::query()->with('company');
        $result = $this->indexQuery($request, $query, ['code', 'name'], ['company_id' => 'company_id']);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreOfficeRequest $request): JsonResponse
    {
        $office = Office::create($request->validated());

        return $this->successResponse($office->load('company'), 'Office created successfully', 201);
    }

    public function show(string $office): JsonResponse
    {
        $model = Office::with('company', 'manager')->find($office);
        if (! $model) {
            return $this->notFoundResponse('Office not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateOfficeRequest $request, string $office): JsonResponse
    {
        $model = Office::find($office);
        if (! $model) {
            return $this->notFoundResponse('Office not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['company', 'manager']), 'Office updated successfully');
    }

    public function destroy(string $office): JsonResponse
    {
        $model = Office::find($office);
        if (! $model) {
            return $this->notFoundResponse('Office not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Office deleted successfully');
    }
}
