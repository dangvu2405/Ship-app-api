<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\PayrollAdjustment\RejectPayrollAdjustmentRequest;
use App\Http\Requests\PayrollAdjustment\StorePayrollAdjustmentRequest;
use App\Http\Requests\PayrollAdjustment\UpdatePayrollAdjustmentRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PayrollAdjustmentController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'company_id', 'driver_id', 'type', 'amount', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = PayrollAdjustment::query()->with(['driver', 'payroll', 'approver']);
        $result = $this->indexQuery($request, $query, ['reason'], [
            'driver_id' => 'driver_id',
            'payroll_id' => 'payroll_id',
            'type' => 'type',
            'company_id' => 'company_id',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(StorePayrollAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['category'] ??= 'manual';

        $payroll = Payroll::find($data['payroll_id']);
        $data['company_id'] = $payroll->company_id;

        $adjustment = PayrollAdjustment::create($data);

        return $this->successResponse($adjustment->load(['driver', 'payroll']), 'Payroll adjustment created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $model = PayrollAdjustment::with(['driver', 'payroll', 'approver'])->find($id);
        if (! $model) {
            return $this->notFoundResponse('Payroll adjustment not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdatePayrollAdjustmentRequest $request, string $id): JsonResponse
    {
        $model = PayrollAdjustment::find($id);
        if (! $model) {
            return $this->notFoundResponse('Payroll adjustment not found');
        }

        if ($model->approved_by !== null) {
            return $this->errorResponse('Approved adjustments cannot be modified', 422);
        }

        $model->update($request->validated());

        return $this->successResponse($model->fresh(['driver', 'payroll']), 'Updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $model = PayrollAdjustment::find($id);
        if (! $model) {
            return $this->notFoundResponse('Payroll adjustment not found');
        }

        if ($model->approved_by !== null) {
            return $this->errorResponse('Approved adjustments cannot be deleted', 422);
        }

        $model->delete();

        return $this->successResponse(null, 'Payroll adjustment deleted');
    }

    public function approve(string $id): JsonResponse
    {
        $model = PayrollAdjustment::find($id);
        if (! $model) {
            return $this->notFoundResponse('Payroll adjustment not found');
        }

        if ($model->approved_by !== null) {
            return $this->errorResponse('Adjustment already approved', 422);
        }

        $model->update(['approved_by' => auth()->id()]);

        return $this->successResponse($model->fresh(['driver', 'payroll', 'approver']), 'Approved');
    }

    public function reject(RejectPayrollAdjustmentRequest $request, string $id): JsonResponse
    {
        $model = PayrollAdjustment::find($id);
        if (! $model) {
            return $this->notFoundResponse('Payroll adjustment not found');
        }

        $model->delete();

        return $this->successResponse(null, 'Adjustment rejected and removed');
    }
}
