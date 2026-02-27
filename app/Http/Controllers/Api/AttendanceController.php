<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'employee_id', 'date', 'status', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Attendance::query()->with('employee');
        $result = $this->indexQuery($request, $query, [], [
            'employee_id' => 'employee_id',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $attendance = Attendance::create($request->validated());

        return $this->successResponse($attendance->load('employee'), 'Attendance created successfully', 201);
    }

    public function show(string $attendance): JsonResponse
    {
        $model = Attendance::with('employee')->find($attendance);
        if (! $model) {
            return $this->notFoundResponse('Attendance not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateAttendanceRequest $request, string $attendance): JsonResponse
    {
        $model = Attendance::find($attendance);
        if (! $model) {
            return $this->notFoundResponse('Attendance not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh('employee'), 'Attendance updated successfully');
    }

    public function destroy(string $attendance): JsonResponse
    {
        $model = Attendance::find($attendance);
        if (! $model) {
            return $this->notFoundResponse('Attendance not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Attendance deleted successfully');
    }
}
