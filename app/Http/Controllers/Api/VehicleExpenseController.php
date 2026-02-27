<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\VehicleExpense\StoreVehicleExpenseRequest;
use App\Http\Requests\VehicleExpense\UpdateVehicleExpenseRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\VehicleExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleExpenseController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'vehicle_id', 'driver_id', 'type', 'amount', 'expense_date', 'created_at'];

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

    public function store(StoreVehicleExpenseRequest $request): JsonResponse
    {
        $expense = VehicleExpense::create($request->validated());

        return $this->successResponse($expense->load(['vehicle', 'driver']), 'Vehicle expense created successfully', 201);
    }

    public function show(string $vehicle_expense): JsonResponse
    {
        $model = VehicleExpense::with(['vehicle', 'driver'])->find($vehicle_expense);
        if (! $model) {
            return $this->notFoundResponse('Vehicle expense not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateVehicleExpenseRequest $request, string $vehicle_expense): JsonResponse
    {
        $model = VehicleExpense::find($vehicle_expense);
        if (! $model) {
            return $this->notFoundResponse('Vehicle expense not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['vehicle', 'driver']), 'Vehicle expense updated successfully');
    }

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
