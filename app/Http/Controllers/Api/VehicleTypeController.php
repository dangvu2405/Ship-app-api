<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\VehicleTypeResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class VehicleTypeController extends BaseController
{
    public function index(Request $request): JsonResource
    {
        $this->authorize('viewAny', VehicleType::class);

        $query = VehicleType::query()
            ->where('company_id', auth()->user()->company_id);

        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%' . $request->input('keyword') . '%');
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');

        $allowedSortColumns = ['name', 'sort_order', 'is_active', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'name';
        }

        $query->orderBy($sortBy, $sortOrder);

        $vehicleTypes = $query->paginate($perPage);

        return VehicleTypeResource::collection($vehicleTypes);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', VehicleType::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $vehicleType = DB::transaction(function () use ($validated) {
            $vehicleType = VehicleType::create(array_merge($validated, [
                'company_id' => auth()->user()->company_id,
            ]));
            return $vehicleType;
        });

        return (new VehicleTypeResource($vehicleType))
            ->response()
            ->setStatusCode(201);
    }

    public function show(VehicleType $vehicleType): JsonResource
    {
        $this->authorize('view', $vehicleType);

        return new VehicleTypeResource($vehicleType);
    }

    public function update(Request $request, VehicleType $vehicleType): JsonResource
    {
        $this->authorize('update', $vehicleType);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sort_order' => 'sometimes|nullable|integer',
            'is_active' => 'sometimes|nullable|boolean',
        ]);

        DB::transaction(function () use ($validated, $vehicleType) {
            $vehicleType->update($validated);
        });

        return new VehicleTypeResource($vehicleType);
    }

    public function destroy(VehicleType $vehicleType): JsonResponse
    {
        $this->authorize('delete', $vehicleType);

        DB::transaction(function () use ($vehicleType) {
            $vehicleType->delete();
        });

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('updateAny', VehicleType::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:vehicle_types,id',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['ids'] as $index => $id) {
                VehicleType::where('id', $id)
                    ->where('company_id', auth()->user()->company_id)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['message' => 'Vehicle types reordered successfully.']);
    }
}
