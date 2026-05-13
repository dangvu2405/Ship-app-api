<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Trip\AssignTripRequest;
use App\Http\Requests\Trip\CancelTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\Trip\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TripController extends BaseController
{
    public function __construct(private readonly TripService $tripService) {}

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
                'company_id' => auth()->user()->company_id,
                'code' => 'TRIP-'.strtoupper(Str::random(8)),
                'customer_id' => $validated['customer_id'],
                'start_point' => $pickup['address'] ?? ($stops[0]['address'] ?? ''),
                'end_point' => $delivery['address'] ?? (end($stops)['address'] ?? ''),
                'price' => $validated['base_price'],
                'received_date' => $validated['received_date'],
                'scheduled_date' => $validated['scheduled_date'],
                'base_price' => $validated['base_price'],
                'surcharge_amount' => $totalSurcharge,
                'total_revenue' => $totalRevenue,
                'status' => 'new',
            ]);

            if (! empty($validated['stops'])) {
                $trip->stops()->createMany($validated['stops']);
            }

            if (! empty($validated['surcharges'])) {
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

    public function assign(AssignTripRequest $request, int $id): JsonResponse
    {
        try {
            $trip = Trip::query()->findOrFail($id);
            $this->authorize('update', $trip);

            $assigned = $this->tripService->assignDriverAndVehicle(
                $trip->id,
                (int) $request->validated('driver_id'),
                (int) $request->validated('vehicle_id'),
                $request->user(),
            );

            return $this->successResponse(new TripResource($assigned), 'api.common.ok');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function start(Request $request, int $id): JsonResponse
    {
        return $this->transitionTrip($request, $id, 'in_progress', ['start_time' => now()]);
    }

    public function deliver(Request $request, int $id): JsonResponse
    {
        return $this->transitionTrip($request, $id, 'delivered', ['actual_delivered_at' => now()]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        return $this->transitionTrip($request, $id, 'completed', ['end_time' => now()]);
    }

    public function cancel(CancelTripRequest $request, int $id): JsonResponse
    {
        try {
            $trip = Trip::query()->findOrFail($id);
            $this->authorize('update', $trip);

            if ($trip->status === 'completed') {
                return $this->validationErrorResponse([
                    'status' => ['Không thể hủy chuyến đã hoàn thành.'],
                ]);
            }

            $fromStatus = $trip->status;
            $updated = DB::transaction(function () use ($trip, $request, $fromStatus): Trip {
                $trip->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => (string) $request->validated('reason'),
                    'cancelled_at' => now(),
                    'cancelled_by' => $request->user()?->id,
                ]);
                DB::table('trip_status_histories')->insert([
                    'trip_id' => $trip->id,
                    'from_status' => $fromStatus,
                    'to_status' => 'cancelled',
                    'changed_by' => $request->user()?->id,
                    'changed_at' => now(),
                    'note' => 'Cancelled: '.$request->validated('reason'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $trip->fresh(['driver', 'vehicle', 'customer']);
            });

            return $this->successResponse(new TripResource($updated), 'api.common.ok', 200);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function changeVehicle(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
        ]);

        return $this->updateAssignmentPart($request, $id, ['vehicle_id' => (int) $validated['vehicle_id']], 'Changed vehicle');
    }

    public function changeDriver(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);

        return $this->updateAssignmentPart($request, $id, ['driver_id' => (int) $validated['driver_id']], 'Changed driver');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transitionTrip(Request $request, int $id, string $status, array $extra = []): JsonResponse
    {
        try {
            $trip = Trip::query()->findOrFail($id);
            $this->authorize('update', $trip);

            $fromStatus = $trip->status;
            $updated = DB::transaction(function () use ($trip, $status, $extra, $request, $fromStatus): Trip {
                $trip->update(['status' => $status, ...$extra]);
                DB::table('trip_status_histories')->insert([
                    'trip_id' => $trip->id,
                    'from_status' => $fromStatus,
                    'to_status' => $status,
                    'changed_by' => $request->user()?->id,
                    'changed_at' => now(),
                    'note' => $request->input('note'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $trip->fresh(['driver', 'vehicle', 'customer']);
            });

            return $this->successResponse(new TripResource($updated), 'api.common.ok');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * @param  array<string, int>  $attributes
     */
    private function updateAssignmentPart(Request $request, int $id, array $attributes, string $note): JsonResponse
    {
        try {
            $trip = Trip::query()->findOrFail($id);
            $this->authorize('update', $trip);

            $updated = DB::transaction(function () use ($trip, $attributes, $request, $note): Trip {
                $trip->update($attributes);
                DB::table('trip_status_histories')->insert([
                    'trip_id' => $trip->id,
                    'from_status' => $trip->status,
                    'to_status' => $trip->status,
                    'changed_by' => $request->user()?->id,
                    'changed_at' => now(),
                    'note' => $note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $trip->fresh(['driver', 'vehicle', 'customer']);
            });

            return $this->successResponse(new TripResource($updated), 'api.common.ok');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}
