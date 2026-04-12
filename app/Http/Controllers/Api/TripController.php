<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Trips", description="Quản lý chuyến xe")
 */
class TripController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'customer_id', 'driver_id', 'vehicle_id', 'status', 'start_time', 'price', 'created_at'];

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

        if ($request->filled('office_id')) {
            $officeId = (int) $request->input('office_id');
            $query->whereHas('vehicle', static function (Builder $q) use ($officeId): void {
                $q->where('office_id', $officeId);
            });
        }

        if ($request->filled('company_id')) {
            $companyId = (int) $request->input('company_id');
            $query->whereHas('vehicle.office', static function (Builder $q) use ($companyId): void {
                $q->where('company_id', $companyId);
            });
        }

        $result = $this->indexQuery($request, $query, ['code', 'start_point', 'end_point'], [
            'customer_id' => 'customer_id',
            'driver_id' => 'driver_id',
            'vehicle_id' => 'vehicle_id',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'OK');
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
        $trip = Trip::create($request->validated());

        return $this->successResponse($trip->load(['customer', 'driver', 'vehicle']), 'Trip created successfully', 201);
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
        if (! $model) {
            return $this->notFoundResponse('Trip not found');
        }

        return $this->successResponse($model);
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
        if (! $model) {
            return $this->notFoundResponse('Trip not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['customer', 'driver', 'vehicle']), 'Trip updated successfully');
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
        if (! $model) {
            return $this->notFoundResponse('Trip not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Trip deleted successfully');
    }
}
