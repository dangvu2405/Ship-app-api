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
            ->where('company_id', app(\App\Tenancy\TenantContext::class)->getCompanyId())
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
            'max_load_ton' => 'nullable|numeric|min:0',
            'current_odometer_km' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:active,maintenance,inactive,broken,out_of_service',
            'office_id' => 'required|integer',
            'vehicle_type_id' => 'nullable|integer',
            'image_url' => 'nullable|string',
        ]);

        $vehicle = DB::transaction(function () use ($validated) {
            $vehicle = Vehicle::create(array_merge($validated, [
                'company_id' => app(\App\Tenancy\TenantContext::class)->getCompanyId() ?? 1,
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
            'max_load_ton' => 'sometimes|nullable|numeric|min:0',
            'current_odometer_km' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|nullable|string|in:active,maintenance,inactive,broken,out_of_service',
            'office_id' => 'sometimes|required|integer',
            'vehicle_type_id' => 'sometimes|nullable|integer',
            'image_url' => 'sometimes|nullable|string',
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

    public function available(Request $request): JsonResource
    {
        $vehicles = Vehicle::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('status', 'active')
            ->paginate(15);

        return VehicleResource::collection($vehicles);
    }

    public function updateStatus(Request $request, Vehicle $vehicle): JsonResource
    {
        $this->authorize('update', $vehicle);

        $validated = $request->validate([
            'status' => 'required|string|in:active,maintenance,inactive',
        ]);

        DB::transaction(function () use ($validated, $vehicle) {
            $vehicle->update(['status' => $validated['status']]);
        });

        return new VehicleResource($vehicle);
    }
}
