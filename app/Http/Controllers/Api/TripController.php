<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Trip\CreateTripDTO;
use App\Http\Resources\Trip\TripResource;
use App\Models\Trip;
use App\Services\Trip\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class TripController extends BaseController
{
    public function __construct(
        protected TripService $tripService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Trip::class);

        $trips = Trip::with(['customer', 'driver', 'vehicle'])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return TripResource::collection($trips);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTripRequest $request): TripResource
    {
        $this->authorize('create', Trip::class);

        $dto = CreateTripDTO::fromArray($request->validated());
        
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        $trip = $this->tripService->createTrip($dto, $user);

        return new TripResource($trip->load(['customer', 'stops', 'surcharges']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Trip $trip): TripResource
    {
        $this->authorize('view', $trip);

        return new TripResource($trip->load(['customer', 'driver', 'vehicle', 'stops', 'surcharges']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Trip $trip): TripResource
    {
        $this->authorize('update', $trip);

        $trip->update($request->all());

        return new TripResource($trip->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip);

        $trip->delete();

        return $this->successResponse(null, 'api.trip.deleted');
    }

    /**
     * Assign driver and vehicle.
     */
    public function assign(Request $request, int $id): TripResource
    {
        $trip = Trip::findOrFail($id);
        $this->authorize('assign', $trip);

        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $trip = $this->tripService->assignDriverAndVehicle(
            (int) $id,
            (int) $data['driver_id'],
            (int) $data['vehicle_id'],
            $user
        );

        return new TripResource($trip);
    }

    /**
     * Transition status to 'in_progress' (Start).
     */
    public function start(int $id): TripResource
    {
        $trip = Trip::findOrFail($id);
        $this->authorize('update', $trip);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        $trip = $this->tripService->transitionStatus($id, 'in_progress', $user, 'Trip started');

        return new TripResource($trip);
    }

    /**
     * Transition status to 'completed'.
     */
    public function complete(int $id): TripResource
    {
        $trip = Trip::findOrFail($id);
        $this->authorize('update', $trip);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        $trip = $this->tripService->transitionStatus($id, 'completed', $user, 'Trip completed');

        return new TripResource($trip);
    }

    /**
     * Cancel a trip.
     */
    public function cancel(Request $request, int $id): TripResource
    {
        $trip = Trip::findOrFail($id);
        $this->authorize('update', $trip);

        $data = $request->validate(['reason' => ['nullable', 'string']]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        $trip = $this->tripService->transitionStatus($id, 'cancelled', $user, $data['reason'] ?? 'Cancelled');

        return new TripResource($trip);
    }
    
    // Additional methods (pickup, transit, arrive, etc.) would follow the same pattern:
    // authorization check -> call service method -> return resource.
}
