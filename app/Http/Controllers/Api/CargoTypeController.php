<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\CargoType\StoreCargoTypeRequest;
use App\Http\Requests\CargoType\UpdateCargoTypeRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\CargoType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CargoTypeController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'sort_order', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $result = $this->indexQuery($request, CargoType::query(), ['name'], []);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreCargoTypeRequest $request): JsonResponse
    {
        $cargoType = CargoType::query()->create($request->validated());

        return $this->successResponse($cargoType, 'Cargo type created successfully', 201);
    }

    public function show(string $cargoType): JsonResponse
    {
        $model = CargoType::query()->find($cargoType);
        if ($model === null) {
            return $this->notFoundResponse('Cargo type not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateCargoTypeRequest $request, string $cargoType): JsonResponse
    {
        $model = CargoType::query()->find($cargoType);
        if ($model === null) {
            return $this->notFoundResponse('Cargo type not found');
        }

        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Cargo type updated successfully');
    }

    public function destroy(string $cargoType): JsonResponse
    {
        $model = CargoType::query()->find($cargoType);
        if ($model === null) {
            return $this->notFoundResponse('Cargo type not found');
        }

        $model->delete();

        return $this->successResponse(null, 'Cargo type deleted successfully');
    }
}
