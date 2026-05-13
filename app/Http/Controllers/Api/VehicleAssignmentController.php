<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\VehicleAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\VehicleAssignmentResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class VehicleAssignmentController extends BaseController
{
    public function __construct()
    {
        $this->authorizeResource(VehicleAssignment::class, 'vehicle_assignment');
    }

    public function index(Request $request): JsonResource
    {
        $vehicleAssignments = VehicleAssignment::query()
            ->where('company_id', app(\App\Tenancy\TenantContext::class)->getCompanyId())
            ->paginate(15);

        return VehicleAssignmentResource::collection($vehicleAssignments);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'driver_id' => 'required|exists:drivers,id',
            'from_date' => 'required|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'notes' => 'nullable|string|max:500',
        ]);

        $vehicleAssignment = DB::transaction(function () use ($validated) {
            $vehicleAssignment = VehicleAssignment::create(array_merge($validated, [
                'company_id' => app(\App\Tenancy\TenantContext::class)->getCompanyId() ?? 1,
            ]));
            return $vehicleAssignment;
        });

        return (new VehicleAssignmentResource($vehicleAssignment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(VehicleAssignment $vehicleAssignment): JsonResource
    {
        return new VehicleAssignmentResource($vehicleAssignment);
    }

    public function update(Request $request, VehicleAssignment $vehicleAssignment): JsonResource
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'vehicle_id' => 'sometimes|required|exists:vehicles,id',
            'driver_id' => 'sometimes|required|exists:drivers,id',
            'from_date' => 'sometimes|required|date',
            'to_date' => 'sometimes|nullable|date|after_or_equal:from_date',
            'notes' => 'sometimes|nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated, $vehicleAssignment) {
            $vehicleAssignment->update($validated);
        });

        return new VehicleAssignmentResource($vehicleAssignment);
    }

    public function destroy(VehicleAssignment $vehicleAssignment): JsonResponse
    {
        DB::transaction(function () use ($vehicleAssignment) {
            $vehicleAssignment->delete();
        });

        return response()->json(null, 204);
    }

    public function release(Request $request, VehicleAssignment $vehicleAssignment): JsonResource
    {
        $validated = $request->validate([
            'release_reason' => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($validated, $vehicleAssignment) {
            $vehicleAssignment->update([
                'to_date' => now()->toDateString(),
                'release_reason' => $validated['release_reason'],
            ]);
        });

        return new VehicleAssignmentResource($vehicleAssignment);
    }
}
