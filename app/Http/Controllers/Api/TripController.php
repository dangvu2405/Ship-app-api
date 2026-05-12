<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\TripResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TripController extends BaseController
{
    public function index(Request $request): JsonResource
    {
        // TODO: Add filtering, sorting, and searching
        $trips = Trip::query()
            ->where('company_id', auth()->user()->company_id)
            ->with(['customer', 'driver', 'vehicle'])
            ->paginate(15);

        return TripResource::collection($trips);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'received_date' => 'required|date',
            'scheduled_date' => 'required|date',
            'base_price' => 'required|numeric|min:0',
            'stops' => 'required|array|min:1',
            'stops.*.stop_type' => 'required|string|in:pickup,delivery',
            'stops.*.sequence' => 'required|integer',
            'stops.*.address' => 'required|string|max:255',
            'surcharges' => 'nullable|array',
            'surcharges.*.name' => 'required|string|max:255',
            'surcharges.*.amount' => 'required|numeric|min:0',
        ]);

        $trip = DB::transaction(function () use ($validated) {
            $totalSurcharge = collect($validated['surcharges'] ?? [])->sum('amount');
            $totalRevenue = $validated['base_price'] + $totalSurcharge;

            $stops = $validated['stops'] ?? [];
            $pickup = collect($stops)->firstWhere('stop_type', 'pickup');
            $delivery = collect($stops)->firstWhere('stop_type', 'delivery');

            $trip = Trip::create([
                'company_id'     => auth()->user()->company_id,
                'code'           => 'TRIP-' . strtoupper(Str::random(8)),
                'customer_id'    => $validated['customer_id'],
                'start_point'    => $pickup['address'] ?? ($stops[0]['address'] ?? ''),
                'end_point'      => $delivery['address'] ?? (end($stops)['address'] ?? ''),
                'price'          => $validated['base_price'],
                'received_date'  => $validated['received_date'],
                'scheduled_date' => $validated['scheduled_date'],
                'base_price'     => $validated['base_price'],
                'surcharge_amount' => $totalSurcharge,
                'total_revenue'  => $totalRevenue,
                'status'         => 'new',
            ]);

            if (!empty($validated['stops'])) {
                $trip->stops()->createMany($validated['stops']);
            }

            if (!empty($validated['surcharges'])) {
                $trip->surcharges()->createMany($validated['surcharges']);
            }

            return $trip;
        });

        return (new TripResource($trip->load(['stops', 'surcharges'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Trip $trip): JsonResource
    {
        $this->authorize('view', $trip); // Requires TripPolicy

        return new TripResource($trip->load(['customer', 'driver', 'vehicle', 'stops', 'surcharges', 'costs', 'documents']));
    }

    public function update(Request $request, Trip $trip): JsonResource
    {
        $this->authorize('update', $trip); // Requires TripPolicy

        // TODO: Add validation and logic
        // This is a placeholder implementation
        $trip->update($request->all());

        return new TripResource($trip);
    }

    public function destroy(Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip); // Requires TripPolicy

        DB::transaction(function () use ($trip) {
            // Manually delete related records if no cascade is set
            $trip->stops()->delete();
            $trip->surcharges()->delete();
            $trip->costs()->delete();
            $trip->documents()->delete();
            $trip->delete();
        });

        return response()->json(null, 204);
    }
}
