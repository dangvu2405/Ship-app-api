<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Location;
use App\Models\RouteTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $result = $this->indexQuery($request, Location::query(), ['name', 'address', 'province', 'district'], []);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = Location::query()->create($request->validated());

        return $this->successResponse($location, 'Location created successfully', 201);
    }

    public function show(string $location): JsonResponse
    {
        $model = Location::query()->find($location);
        if ($model === null) {
            return $this->notFoundResponse('Location not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateLocationRequest $request, string $location): JsonResponse
    {
        $model = Location::query()->find($location);
        if ($model === null) {
            return $this->notFoundResponse('Location not found');
        }

        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Location updated successfully');
    }

    public function destroy(string $location): JsonResponse
    {
        $model = Location::query()->find($location);
        if ($model === null) {
            return $this->notFoundResponse('Location not found');
        }

        RouteTemplate::query()
            ->where('origin_location_id', $model->id)
            ->update(['origin_location_id' => null]);
        RouteTemplate::query()
            ->where('destination_location_id', $model->id)
            ->update(['destination_location_id' => null]);

        $model->delete();

        return $this->successResponse(null, 'Location deleted successfully');
    }
}
