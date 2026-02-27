<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\VehicleAssignment\StoreVehicleAssignmentRequest;
use App\Http\Requests\VehicleAssignment\UpdateVehicleAssignmentRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\VehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleAssignmentController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'vehicle_id', 'driver_id', 'from_date', 'to_date', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = VehicleAssignment::query()->with(['vehicle', 'driver']);
        $result = $this->indexQuery($request, $query, [], [
            'vehicle_id' => 'vehicle_id',
            'driver_id' => 'driver_id',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreVehicleAssignmentRequest $request): JsonResponse
    {
        $assignment = VehicleAssignment::create($request->validated());

        return $this->successResponse($assignment->load(['vehicle', 'driver']), 'Vehicle assignment created successfully', 201);
    }

    public function show(string $vehicle_assignment): JsonResponse
    {
        $model = VehicleAssignment::with(['vehicle', 'driver'])->find($vehicle_assignment);
        if (! $model) {
            return $this->notFoundResponse('Vehicle assignment not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateVehicleAssignmentRequest $request, string $vehicle_assignment): JsonResponse
    {
        $model = VehicleAssignment::find($vehicle_assignment);
        if (! $model) {
            return $this->notFoundResponse('Vehicle assignment not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['vehicle', 'driver']), 'Vehicle assignment updated successfully');
    }

    public function destroy(string $vehicle_assignment): JsonResponse
    {
        $model = VehicleAssignment::find($vehicle_assignment);
        if (! $model) {
            return $this->notFoundResponse('Vehicle assignment not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Vehicle assignment deleted successfully');
    }
}
