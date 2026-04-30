<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\RouteTemplate\StoreRouteTemplateRequest;
use App\Http\Requests\RouteTemplate\UpdateRouteTemplateRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Location;
use App\Models\RouteTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RouteTemplateController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'distance_km', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $result = $this->indexQuery($request, RouteTemplate::query(), ['name'], []);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreRouteTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['distance_km'] = $this->resolveDistanceKm($data);

        $routeTemplate = RouteTemplate::query()->create($data);

        return $this->successResponse($routeTemplate, 'Route template created successfully', 201);
    }

    public function show(string $routeTemplate): JsonResponse
    {
        $model = RouteTemplate::query()->find($routeTemplate);
        if ($model === null) {
            return $this->notFoundResponse('Route template not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateRouteTemplateRequest $request, string $routeTemplate): JsonResponse
    {
        $model = RouteTemplate::query()->find($routeTemplate);
        if ($model === null) {
            return $this->notFoundResponse('Route template not found');
        }

        $data = $request->validated();
        if (! array_key_exists('distance_km', $data)) {
            $data['distance_km'] = $this->resolveDistanceKm([
                'origin_location_id' => $data['origin_location_id'] ?? $model->origin_location_id,
                'destination_location_id' => $data['destination_location_id'] ?? $model->destination_location_id,
            ]);
        }

        $model->update($data);

        return $this->successResponse($model->fresh(), 'Route template updated successfully');
    }

    public function destroy(string $routeTemplate): JsonResponse
    {
        $model = RouteTemplate::query()->find($routeTemplate);
        if ($model === null) {
            return $this->notFoundResponse('Route template not found');
        }

        $model->delete();

        return $this->successResponse(null, 'Route template deleted successfully');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDistanceKm(array $data): ?float
    {
        if (array_key_exists('distance_km', $data) && $data['distance_km'] !== null) {
            return (float) $data['distance_km'];
        }

        $origin = Location::query()->find($data['origin_location_id'] ?? null);
        $destination = Location::query()->find($data['destination_location_id'] ?? null);
        if ($origin === null || $destination === null || $origin->lat === null || $origin->lng === null || $destination->lat === null || $destination->lng === null) {
            return null;
        }

        return round($this->haversineKm((float) $origin->lat, (float) $origin->lng, (float) $destination->lat, (float) $destination->lng), 2);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadiusKm * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
