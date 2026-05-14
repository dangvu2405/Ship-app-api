<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Trip\AssignTripRequest;
use App\Http\Requests\Trip\CancelTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\Trip\TripService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TripController extends BaseController
{
    public function __construct(
        private readonly TripService $tripService,
        private readonly TenantContext $tenantContext,
    ) {}

    private function resolveCompanyId(Request $request): ?int
    {
        return $this->tenantContext->getCompanyId()
            ?? $request->user()?->getAttribute('company_id');
    }

    public function index(Request $request): JsonResource
    {
        $companyId = $this->resolveCompanyId($request);
        $trips = Trip::query()
            ->where('company_id', $companyId)
            ->with(['customer', 'driver', 'vehicle'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->input('customer_id')))
            ->when($request->filled('driver_id'), fn ($q) => $q->where('driver_id', $request->input('driver_id')))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->input('vehicle_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('scheduled_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('scheduled_date', '<=', $request->input('date_to')))
            ->latest('id')
            ->paginate((int) $request->input('per_page', 15));

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

        $companyId = $this->resolveCompanyId($request);

        $trip = DB::transaction(function () use ($validated, $companyId) {
            $totalSurcharge = collect($validated['surcharges'] ?? [])->sum('amount');
            $totalRevenue = $validated['base_price'] + $totalSurcharge;

            $stops = $validated['stops'] ?? [];
            $pickup = collect($stops)->firstWhere('stop_type', 'pickup');
            $delivery = collect($stops)->firstWhere('stop_type', 'delivery');

            $trip = Trip::create([
                'company_id' => $companyId,
                'code' => $this->generateUniqueCode(),
                'customer_id' => $validated['customer_id'],
                'start_point' => $pickup['address'] ?? ($stops[0]['address'] ?? ''),
                'end_point' => $delivery['address'] ?? (end($stops)['address'] ?? ''),
                'price' => $validated['base_price'],
                'received_date' => $validated['received_date'],
                'scheduled_date' => $validated['scheduled_date'],
                'base_price' => $validated['base_price'],
                'surcharge_amount' => $totalSurcharge,
                'total_revenue' => $totalRevenue,
                'status' => 'pending',
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
        $this->authorize('update', $trip);

        if (in_array($trip->status, ['completed', 'delivered'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Không thể chỉnh sửa chuyến đã giao/hoàn thành.'],
            ]);
        }

        $validated = $request->validate([
            'customer_id'    => 'sometimes|exists:customers,id',
            'received_date'  => 'sometimes|date',
            'scheduled_date' => 'sometimes|date',
            'base_price'     => 'sometimes|numeric|min:0',
            'start_point'    => 'sometimes|string|max:255',
            'end_point'      => 'sometimes|string|max:255',
            'distance_km'    => 'sometimes|nullable|numeric|min:0',
            'notes'          => 'sometimes|nullable|string',
        ]);

        if (isset($validated['base_price'])) {
            $surchargeAmount = $trip->surcharge_amount ?? 0;
            $validated['total_revenue'] = $validated['base_price'] + $surchargeAmount;
        }

        $trip->update($validated);

        return new TripResource($trip);
    }

    public function destroy(Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip);

        // R01: không xoá trip đã hoàn thành hoặc đã giao
        if (in_array($trip->status, ['completed', 'delivered'], true)) {
            return response()->json([
                'message' => 'Không thể xoá chuyến đã giao hoặc đã hoàn thành.',
            ], 422);
        }

        DB::transaction(function () use ($trip) {
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
            $trip = Trip::query()->where('company_id', $this->resolveCompanyId($request))->findOrFail($id);
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
            $trip = Trip::query()->where('company_id', $this->resolveCompanyId($request))->findOrFail($id);
            $this->authorize('update', $trip);

            if (in_array($trip->status, ['completed', 'delivered'], true)) {
                return $this->validationErrorResponse([
                    'status' => ['Không thể hủy chuyến đã giao hoặc đã hoàn thành.'],
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
            $trip = Trip::query()->where('company_id', $this->resolveCompanyId($request))->findOrFail($id);
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

    private function generateUniqueCode(): string
    {
        do {
            $code = 'TRIP-'.strtoupper(Str::random(8));
        } while (Trip::where('code', $code)->exists());

        return $code;
    }

    /**
     * @param  array<string, int>  $attributes
     */
    private function updateAssignmentPart(Request $request, int $id, array $attributes, string $note): JsonResponse
    {
        try {
            $trip = Trip::query()->where('company_id', $this->resolveCompanyId($request))->findOrFail($id);
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
