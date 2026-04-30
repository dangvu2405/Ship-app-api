<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\VehicleType\StoreVehicleTypeRequest;
use App\Http\Requests\VehicleType\UpdateVehicleTypeRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VehicleTypeController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'sort_order', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $result = $this->indexQuery($request, VehicleType::query(), ['name', 'required_license_class'], []);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreVehicleTypeRequest $request): JsonResponse
    {
        $vehicleType = VehicleType::query()->create($request->validated());

        return $this->successResponse($vehicleType, 'Vehicle type created successfully', 201);
    }

    public function show(string $vehicleType): JsonResponse
    {
        $model = VehicleType::query()->find($vehicleType);
        if ($model === null) {
            return $this->notFoundResponse('Vehicle type not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateVehicleTypeRequest $request, string $vehicleType): JsonResponse
    {
        $model = VehicleType::query()->find($vehicleType);
        if ($model === null) {
            return $this->notFoundResponse('Vehicle type not found');
        }

        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Vehicle type updated successfully');
    }

    public function destroy(string $vehicleType): JsonResponse
    {
        $model = VehicleType::query()->find($vehicleType);
        if ($model === null) {
            return $this->notFoundResponse('Vehicle type not found');
        }

        if ($model->vehicles()->exists()) {
            return $this->errorResponse('Vehicle type is in use by vehicles', 409);
        }

        $model->delete();

        return $this->successResponse(null, 'Vehicle type deleted successfully');
    }
}
