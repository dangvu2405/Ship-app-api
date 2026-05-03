<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Trip\AssignTripRequest;
use App\Http\Requests\Trip\CancelTripRequest;
use App\Http\Requests\Trip\DelayTripRequest;
use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripDetailsRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Trip;
use App\Models\VehicleAssignment;
use App\Services\Trip\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(name="Trips", description="Quản lý chuyến xe")
 */
class TripController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'customer_id', 'driver_id', 'vehicle_id', 'status', 'start_time', 'price', 'created_at'];

    public function __construct(private readonly TripService $tripService) {}

    private function actorId(): ?int
    {
        $id = Auth::id();

        return $id !== null ? (int) $id : null;
    }

    /**
     * @OA\Get(
     *     path="/api/trips",
     *     tags={"Trips"},
     *     summary="Danh sách chuyến xe",
     *
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code, start_point, end_point", @OA\Schema(type="string")),
     *     @OA\Parameter(name="customer_id", in="query", description="Lọc theo khách hàng", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="driver_id", in="query", description="Lọc theo tài xế", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="vehicle_id", in="query", description="Lọc theo xe", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Trip::query()->with(['customer', 'driver', 'vehicle']);

        // BelongsToTenant đã áp global scope company_id từ TenantContext — không filter thủ công

        if ($request->filled('office_id')) {
            $query->whereHas('vehicle', static function ($q) use ($request): void {
                $q->where('office_id', $request->integer('office_id'));
            });
        }

        $result = $this->indexQuery($request, $query, ['code', 'start_point', 'end_point'], [
            'customer_id' => 'customer_id',
            'driver_id' => 'driver_id',
            'vehicle_id' => 'vehicle_id',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'api.common.ok');
    }

    /**
     * @OA\Post(
     *     path="/api/trips",
     *     tags={"Trips"},
     *     summary="Tạo chuyến xe mới",
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"code","customer_id","driver_id","vehicle_id","start_point","end_point"},
     *
     *             @OA\Property(property="code", type="string", example="TRIP001"),
     *             @OA\Property(property="customer_id", type="integer", example=1),
     *             @OA\Property(property="driver_id", type="integer", example=1),
     *             @OA\Property(property="vehicle_id", type="integer", example=1),
     *             @OA\Property(property="start_point", type="string", example="Hà Nội"),
     *             @OA\Property(property="end_point", type="string", example="Hải Phòng"),
     *             @OA\Property(property="start_time", type="string", format="date-time"),
     *             @OA\Property(property="end_time", type="string", format="date-time"),
     *             @OA\Property(property="distance", type="number"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="status", type="string", enum={"pending","in_progress","completed","cancelled"})
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $warnings = [];

        if (isset($payload['driver_id'])) {
            $expiredDate = \App\Models\Driver::query()->whereKey($payload['driver_id'])->value('expired_date');
            if ($expiredDate !== null && now()->toDateString() > (string) $expiredDate) {
                $warnings[] = 'Driver license is expired. Trip is still allowed but requires follow-up.';
            }
        }

        $trip = Trip::create($payload);
        $responseData = $trip->load(['customer', 'driver', 'vehicle'])->toArray();
        if ($warnings !== []) {
            $responseData['warnings'] = $warnings;
        }

        return $this->successResponse($responseData, 'api.trip.created', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/trips/{id}",
     *     tags={"Trips"},
     *     summary="Chi tiết chuyến xe",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $trip): JsonResponse
    {
        $model = Trip::with(['customer', 'driver', 'vehicle'])->find($trip);
        if (!$model) {
            return $this->notFoundResponse('api.trip.not_found');
        }

        return $this->successResponse($model, 'api.common.ok');
    }

    /**
     * @OA\Put(
     *     path="/api/trips/{id}",
     *     tags={"Trips"},
     *     summary="Cập nhật chuyến xe",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="customer_id", type="integer"),
     *             @OA\Property(property="driver_id", type="integer"),
     *             @OA\Property(property="vehicle_id", type="integer"),
     *             @OA\Property(property="start_point", type="string"),
     *             @OA\Property(property="end_point", type="string"),
     *             @OA\Property(property="start_time", type="string", format="date-time"),
     *             @OA\Property(property="end_time", type="string", format="date-time"),
     *             @OA\Property(property="distance", type="number"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateTripRequest $request, string $trip): JsonResponse
    {
        $model = Trip::find($trip);
        if (!$model) {
            return $this->notFoundResponse('api.trip.not_found');
        }

        $payload = $request->validated();
        unset($payload['code']);

        $model->update($payload);

        return $this->successResponse($model->fresh(['customer', 'driver', 'vehicle']), 'api.trip.updated');
    }

    /**
     * @OA\Delete(
     *     path="/api/trips/{id}",
     *     tags={"Trips"},
     *     summary="Xóa chuyến xe",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $trip): JsonResponse
    {
        $model = Trip::find($trip);
        if (!$model) {
            return $this->notFoundResponse('api.trip.not_found');
        }

        return $this->errorResponse(
            'api.trip.delete_not_allowed',
            422,
            [
                'code' => __('api.errors.code.operation_not_allowed'),
                'details' => [__('api.trip.delete_not_allowed')],
            ],
        );
    }

    public function assign(AssignTripRequest $request, string $id): JsonResponse
    {
        $model = Trip::find($id);
        if (!$model) {
            return $this->notFoundResponse('api.trip.not_found');
        }

        if (in_array($model->status, ['completed', 'cancelled'], true)) {
            return $this->errorResponse(
                'api.trip.assign_not_allowed_from_status',
                422,
                [
                    'code' => __('api.errors.code.operation_not_allowed'),
                    'details' => [__('api.trip.assign_not_allowed_from_status', ['status' => $model->status])],
                ],
            );
        }

        $data = $request->validated();

        if ($model->driver_id !== null && (int) $model->driver_id !== (int) $data['driver_id']) {
            return $this->errorResponse(
                'api.trip.already_assigned',
                422,
                [
                    'code' => __('api.errors.code.operation_not_allowed'),
                    'details' => [__('api.trip.already_assigned')],
                ],
            );
        }

        $driverBusy = Trip::query()
            ->whereKeyNot($model->id)
            ->where('driver_id', $data['driver_id'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();

        if ($driverBusy) {
            return $this->errorResponse(
                'api.trip.driver_conflict',
                422,
                [
                    'code' => __('api.errors.code.dependency_restriction'),
                    'details' => [__('api.trip.driver_conflict')],
                ],
            );
        }

        if ($model->vehicle_id !== null) {
            $tripDate = $model->start_time?->toDateString() ?? now()->toDateString();
            $hasValidAssignment = VehicleAssignment::query()
                ->where('driver_id', $data['driver_id'])
                ->where('vehicle_id', $model->vehicle_id)
                ->whereDate('from_date', '<=', $tripDate)
                ->where(function ($query) use ($tripDate): void {
                    $query->whereNull('to_date')
                        ->orWhereDate('to_date', '>=', $tripDate);
                })
                ->exists();

            if (! $hasValidAssignment) {
                return $this->errorResponse(
                    'api.trip.assignment_mismatch',
                    422,
                    [
                        'code' => __('api.errors.code.dependency_restriction'),
                        'details' => [__('api.trip.assignment_mismatch')],
                    ],
                );
            }

            $vehicleBusy = Trip::query()
                ->whereKeyNot($model->id)
                ->where('vehicle_id', $model->vehicle_id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->exists();

            if ($vehicleBusy) {
                return $this->errorResponse(
                    'api.trip.vehicle_conflict',
                    422,
                    [
                        'code' => __('api.errors.code.dependency_restriction'),
                        'details' => [__('api.trip.vehicle_conflict')],
                    ],
                );
            }
        }

        $fromStatus = $model->status;
        $model->update(['driver_id' => $data['driver_id']]);
        $this->tripService->recordStatusHistory($model->id, $fromStatus, $fromStatus, $this->actorId(), 'Assigned driver');

        return $this->successResponse($model->fresh(['customer', 'driver', 'vehicle']), 'api.trip.driver_assigned');
    }

    public function start(string $id): JsonResponse
    {
        $model = Trip::find($id);
        if (!$model) {
            return $this->notFoundResponse('api.trip.not_found');
        }
        if (!in_array($model->status, ['pending', 'in_progress'], true)) {
            return $this->errorResponse(__('api.trip.cannot_start_from_status', ['status' => $model->status]), 422);
        }

        $fromStatus = $model->status;
        $model->update(['status' => 'in_progress', 'start_time' => $model->start_time ?? now()]);
        $this->tripService->recordStatusHistory($model->id, $fromStatus, 'in_progress', $this->actorId());

        return $this->successResponse($model->fresh(['customer', 'driver', 'vehicle']), 'api.trip.started');
    }

    public function pickup(string $id): JsonResponse
    {
        $model = Trip::find($id);
        if (!$model) {
            return $this->notFoundResponse('api.trip.not_found');
        }

        $this->tripService->recordStatusHistory($model->id, $model->status, $model->status, $this->actorId(), 'Cargo picked up');

        return $this->successResponse($model->load(['customer', 'driver', 'vehicle']), 'api.trip.pickup_recorded');
    }

    public function transit(string $id): JsonResponse
    {
        $model = Trip::find($id);
        if (!$model) {
            return $this->notFoundResponse('api.trip.not_found');
        }

        $this->tripService->recordStatusHistory($model->id, $model->status, $model->status, $this->actorId(), 'In transit');

        return $this->successResponse($model->load(['customer', 'driver', 'vehicle']), 'api.trip.transit_recorded');
    }

    public function arrive(string $id): JsonResponse
    {
        $model = Trip::find($id);
        if (!$model) {
            return $this->notFoundResponse('api.trip.not_found');
        }

        $this->tripService->recordStatusHistory($model->id, $model->status, $model->status, $this->actorId(), 'Arrived at destination');

        return $this->successResponse($model->load(['customer', 'driver', 'vehicle']), 'api.trip.arrival_recorded');
    }

    public function complete(string $id): JsonResponse
    {
        $model = Trip::find($id);
        if (!$model) {
            return $this->notFoundResponse('api.trip.not_found');
        }
        if ($model->status === 'completed') {
            return $this->successResponse($model->load(['customer', 'driver', 'vehicle']), 'api.trip.already_completed');
        }
        if ($model->status === 'cancelled') {
            return $this->errorResponse('api.trip.cannot_complete_cancelled', 422);
        }

        $fromStatus = $model->status;
        $model->update(['status' => 'completed', 'end_time' => $model->end_time ?? now()]);
        $this->tripService->recordStatusHistory($model->id, $fromStatus, 'completed', $this->actorId());

        return $this->successResponse($model->fresh(['customer', 'driver', 'vehicle']), 'api.trip.completed');
    }

    public function cancel(CancelTripRequest $request, string $id): JsonResponse
    {
        $model = Trip::find($id);
        if (! $model) {
            return $this->notFoundResponse('api.trip.not_found');
        }
        if ($model->status === 'completed') {
            return $this->errorResponse('api.trip.cannot_cancel_completed', 422);
        }

        $fromStatus = $model->status;
        $note = $request->validated('reason') ?? 'Cancelled';
        $model->update(['status' => 'cancelled']);
        $this->tripService->recordStatusHistory($model->id, $fromStatus, 'cancelled', $this->actorId(), $note);

        return $this->successResponse($model->fresh(['customer', 'driver', 'vehicle']), 'api.trip.cancelled');
    }

    public function delay(DelayTripRequest $request, string $id): JsonResponse
    {
        $model = Trip::find($id);
        if (! $model) {
            return $this->notFoundResponse('api.trip.not_found');
        }

        $note = $request->validated('reason') ?? 'Delayed';
        $this->tripService->recordStatusHistory($model->id, $model->status, $model->status, $this->actorId(), $note);

        return $this->successResponse($model->load(['customer', 'driver', 'vehicle']), 'api.trip.delay_recorded');
    }

    public function resume(string $id): JsonResponse
    {
        $model = Trip::find($id);
        if (! $model) {
            return $this->notFoundResponse('api.trip.not_found');
        }

        $this->tripService->recordStatusHistory($model->id, $model->status, 'in_progress', $this->actorId(), 'Resumed after delay');

        return $this->successResponse($model->load(['customer', 'driver', 'vehicle']), 'api.trip.resumed');
    }

    /**
     * @OA\Patch(
     *     path="/trips/{id}/details",
     *     summary="Cập nhật thông tin bổ sung chuyến xe",
     *     tags={"Trips"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Trip ID", @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="distance_km", type="number", format="float", nullable=true, minimum=0, description="Cập nhật quãng đường (schema dump trips)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy chuyến xe"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function updateDetails(UpdateTripDetailsRequest $request, string $id): JsonResponse
    {
        $trip = Trip::query()->find($id);
        if (! $trip) {
            return $this->notFoundResponse('api.trip.not_found');
        }

        $trip->update($request->validated());

        return $this->successResponse($trip->fresh(), 'api.common.updated');
    }

    public function deliver(string $id): JsonResponse
    {
        return $this->arrive($id);
    }

    public function changeVehicle(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
        ]);
        $model = Trip::find($id);
        if (! $model) {
            return $this->notFoundResponse('api.trip.not_found');
        }
        if (in_array($model->status, ['completed', 'cancelled'], true)) {
            return $this->errorResponse(
                'api.trip.assign_not_allowed_from_status',
                422,
                [
                    'code' => __('api.errors.code.operation_not_allowed'),
                    'details' => [__('api.trip.assign_not_allowed_from_status', ['status' => $model->status])],
                ],
            );
        }
        $fromStatus = $model->status;
        $model->update(['vehicle_id' => $data['vehicle_id']]);
        $this->tripService->recordStatusHistory($model->id, $fromStatus, $fromStatus, $this->actorId(), 'Vehicle changed');

        return $this->successResponse($model->fresh(['customer', 'driver', 'vehicle']), 'api.common.updated');
    }

    public function changeDriver(AssignTripRequest $request, string $id): JsonResponse
    {
        return $this->assign($request, $id);
    }
}
