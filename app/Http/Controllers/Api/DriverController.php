<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Driver\StoreDriverRequest;
use App\Http\Requests\Driver\UpdateDriverRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'employee_id', 'license_no', 'available_status', 'expired_date', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Driver::query()->with('employee.office');
        $result = $this->indexQuery($request, $query, ['license_no'], [
            'employee_id' => 'employee_id',
            'available_status' => 'available_status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreDriverRequest $request): JsonResponse
    {
        $driver = Driver::create($request->validated());

        return $this->successResponse($driver->load('employee'), 'Driver created successfully', 201);
    }

    public function show(string $driver): JsonResponse
    {
        $model = Driver::with('employee.office')->find($driver);
        if (! $model) {
            return $this->notFoundResponse('Driver not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateDriverRequest $request, string $driver): JsonResponse
    {
        $model = Driver::find($driver);
        if (! $model) {
            return $this->notFoundResponse('Driver not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh('employee'), 'Driver updated successfully');
    }

    public function destroy(string $driver): JsonResponse
    {
        $model = Driver::find($driver);
        if (! $model) {
            return $this->notFoundResponse('Driver not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Driver deleted successfully');
    }
}
