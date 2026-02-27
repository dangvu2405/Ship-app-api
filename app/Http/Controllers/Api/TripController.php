<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'customer_id', 'driver_id', 'vehicle_id', 'status', 'start_time', 'price', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Trip::query()->with(['customer', 'driver', 'vehicle']);
        $result = $this->indexQuery($request, $query, ['code', 'start_point', 'end_point'], [
            'customer_id' => 'customer_id',
            'driver_id' => 'driver_id',
            'vehicle_id' => 'vehicle_id',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = Trip::create($request->validated());

        return $this->successResponse($trip->load(['customer', 'driver', 'vehicle']), 'Trip created successfully', 201);
    }

    public function show(string $trip): JsonResponse
    {
        $model = Trip::with(['customer', 'driver', 'vehicle'])->find($trip);
        if (! $model) {
            return $this->notFoundResponse('Trip not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateTripRequest $request, string $trip): JsonResponse
    {
        $model = Trip::find($trip);
        if (! $model) {
            return $this->notFoundResponse('Trip not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['customer', 'driver', 'vehicle']), 'Trip updated successfully');
    }

    public function destroy(string $trip): JsonResponse
    {
        $model = Trip::find($trip);
        if (! $model) {
            return $this->notFoundResponse('Trip not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Trip deleted successfully');
    }
}
