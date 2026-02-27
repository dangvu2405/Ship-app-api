<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Employee;
use App\Models\PayrollDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'email', 'type', 'status', 'office_id', 'join_date', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()->with(['office', 'department', 'position']);
        $result = $this->indexQuery($request, $query, ['code', 'name', 'email'], [
            'office_id' => 'office_id',
            'department_id' => 'department_id',
            'type' => 'type',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return $this->successResponse($employee->load(['office', 'department', 'position']), 'Employee created successfully', 201);
    }

    public function show(string $employee): JsonResponse
    {
        $model = Employee::with(['office', 'department', 'position', 'driver'])->find($employee);
        if (! $model) {
            return $this->notFoundResponse('Employee not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateEmployeeRequest $request, string $employee): JsonResponse
    {
        $model = Employee::find($employee);
        if (! $model) {
            return $this->notFoundResponse('Employee not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['office', 'department', 'position', 'driver']), 'Employee updated successfully');
    }

    public function destroy(string $employee): JsonResponse
    {
        $model = Employee::find($employee);
        if (! $model) {
            return $this->notFoundResponse('Employee not found');
        }
        if (PayrollDetail::where('employee_id', $employee)->exists()) {
            return $this->errorResponse('Cannot delete employee linked to payroll', 422);
        }
        $model->delete();

        return $this->successResponse(null, 'Employee deleted successfully');
    }
}
