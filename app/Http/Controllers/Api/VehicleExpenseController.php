<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\VehicleExpense\StoreVehicleExpenseRequest;
use App\Http\Requests\VehicleExpense\UpdateVehicleExpenseRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\VehicleExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Vehicle Expenses", description="Quản lý chi phí xe")
 */
class VehicleExpenseController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'vehicle_id', 'driver_id', 'type', 'amount', 'expense_date', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/vehicle_expenses",
     *     tags={"Vehicle Expenses"},
     *     summary="Danh sách chi phí xe",
     *     @OA\Parameter(name="vehicle_id", in="query", description="Lọc theo xe", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="driver_id", in="query", description="Lọc theo tài xế", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", description="Lọc theo loại chi phí", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = VehicleExpense::query()->with(['vehicle', 'driver']);
        $result = $this->indexQuery($request, $query, [], [
            'vehicle_id' => 'vehicle_id',
            'driver_id' => 'driver_id',
            'type' => 'type',
        ]);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/vehicle_expenses",
     *     tags={"Vehicle Expenses"},
     *     summary="Tạo chi phí xe mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"vehicle_id","type","amount","expense_date"},
     *             @OA\Property(property="vehicle_id", type="integer", example=1),
     *             @OA\Property(property="driver_id", type="integer"),
     *             @OA\Property(property="type", type="string", enum={"fuel","maintenance","insurance","other"}, example="fuel"),
     *             @OA\Property(property="amount", type="number", example=500000),
     *             @OA\Property(property="expense_date", type="string", format="date"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="receipt_no", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreVehicleExpenseRequest $request): JsonResponse
    {
        $expense = VehicleExpense::create($request->validated());

        return $this->successResponse($expense->load(['vehicle', 'driver']), 'Vehicle expense created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/vehicle_expenses/{id}",
     *     tags={"Vehicle Expenses"},
     *     summary="Chi tiết chi phí xe",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $vehicle_expense): JsonResponse
    {
        $model = VehicleExpense::with(['vehicle', 'driver'])->find($vehicle_expense);
        if (! $model) {
            return $this->notFoundResponse('Vehicle expense not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/vehicle_expenses/{id}",
     *     tags={"Vehicle Expenses"},
     *     summary="Cập nhật chi phí xe",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="vehicle_id", type="integer"),
     *             @OA\Property(property="driver_id", type="integer"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="amount", type="number"),
     *             @OA\Property(property="expense_date", type="string", format="date"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="receipt_no", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateVehicleExpenseRequest $request, string $vehicle_expense): JsonResponse
    {
        $model = VehicleExpense::find($vehicle_expense);
        if (! $model) {
            return $this->notFoundResponse('Vehicle expense not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['vehicle', 'driver']), 'Vehicle expense updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/vehicle_expenses/{id}",
     *     tags={"Vehicle Expenses"},
     *     summary="Xóa chi phí xe",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function destroy(string $vehicle_expense): JsonResponse
    {
        $model = VehicleExpense::find($vehicle_expense);
        if (! $model) {
            return $this->notFoundResponse('Vehicle expense not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Vehicle expense deleted successfully');
    }
}
