<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\VehicleResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class VehicleController extends BaseController
{
    public function index(Request $request): JsonResource
    {
        $vehicles = Vehicle::query()
            ->where('company_id', auth()->user()->company_id)
            ->paginate(15);

        return VehicleResource::collection($vehicles);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'plate_number' => 'required|string|max:255|unique:vehicles',
            'type' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'capacity' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:active,maintenance,inactive',
        ]);

        $vehicle = DB::transaction(function () use ($validated) {
            $vehicle = Vehicle::create(array_merge($validated, [
                'company_id' => auth()->user()->company_id,
            ]));
            return $vehicle;
        });

        return (new VehicleResource($vehicle))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Vehicle $vehicle): JsonResource
    {
        $this->authorize('view', $vehicle);

        return new VehicleResource($vehicle);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResource
    {
        $this->authorize('update', $vehicle);

        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'plate_number' => 'sometimes|required|string|max:255|unique:vehicles,plate_number,' . $vehicle->id,
            'type' => 'sometimes|required|string|max:255',
            'brand' => 'sometimes|nullable|string|max:255',
            'model' => 'sometimes|nullable|string|max:255',
            'year' => 'sometimes|nullable|integer|min:1900|max:' . (date('Y') + 1),
            'capacity' => 'sometimes|nullable|integer|min:0',
            'status' => 'sometimes|nullable|string|in:active,maintenance,inactive',
        ]);

        DB::transaction(function () use ($validated, $vehicle) {
            $vehicle->update($validated);
        });

        return new VehicleResource($vehicle);
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        DB::transaction(function () use ($vehicle) {
            $vehicle->delete();
        });

        return response()->json(null, 204);
    }
}
