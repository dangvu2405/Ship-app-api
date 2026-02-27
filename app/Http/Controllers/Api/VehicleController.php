<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'plate_number', 'type', 'status', 'office_id', 'year', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::query()->with('office');
        $result = $this->indexQuery($request, $query, ['plate_number', 'brand', 'model'], [
            'office_id' => 'office_id',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = Vehicle::create($request->validated());

        return $this->successResponse($vehicle->load('office'), 'Vehicle created successfully', 201);
    }

    public function show(string $vehicle): JsonResponse
    {
        $model = Vehicle::with('office')->find($vehicle);
        if (! $model) {
            return $this->notFoundResponse('Vehicle not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateVehicleRequest $request, string $vehicle): JsonResponse
    {
        $model = Vehicle::find($vehicle);
        if (! $model) {
            return $this->notFoundResponse('Vehicle not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh('office'), 'Vehicle updated successfully');
    }

    public function destroy(string $vehicle): JsonResponse
    {
        $model = Vehicle::find($vehicle);
        if (! $model) {
            return $this->notFoundResponse('Vehicle not found');
        }
        if (VehicleAssignment::where('vehicle_id', $vehicle)->exists()) {
            return $this->errorResponse('Cannot delete vehicle that is assigned', 422);
        }
        $model->delete();

        return $this->successResponse(null, 'Vehicle deleted successfully');
    }
}
