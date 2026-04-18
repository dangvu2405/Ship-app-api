<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Traits\HasIndexQuery;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollAdjustmentController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'company_id', 'driver_id', 'type', 'amount', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = PayrollAdjustment::query()->with(['driver', 'payroll', 'approver']);
        $result = $this->indexQuery($request, $query, ['reason'], [
            'driver_id'  => 'driver_id',
            'payroll_id' => 'payroll_id',
            'type'       => 'type',
            'company_id' => 'company_id',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payroll_id'  => 'required|exists:payrolls,id',
            'driver_id'   => 'required|exists:drivers,id',
            'type'        => 'required|in:addition,deduction',
            'amount'      => 'required|numeric|min:0',
            'reason'      => 'required|string|max:1000',
            'category'    => 'nullable|in:violation_refund,leave_restore,ot_late_approval,manual',
            'source_type' => 'nullable|string|max:50',
            'source_id'   => 'nullable|integer',
        ]);

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

    public function update(Request $request, string $id): JsonResponse
    {
        $model = PayrollAdjustment::find($id);
        if (! $model) {
            return $this->notFoundResponse('Payroll adjustment not found');
        }

        if ($model->approved_by !== null) {
            return $this->errorResponse('Approved adjustments cannot be modified', 422);
        }

        $data = $request->validate([
            'type'   => 'sometimes|in:addition,deduction',
            'amount' => 'sometimes|numeric|min:0',
            'reason' => 'sometimes|string|max:1000',
        ]);

        $model->update($data);

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

    public function reject(Request $request, string $id): JsonResponse
    {
        $model = PayrollAdjustment::find($id);
        if (! $model) {
            return $this->notFoundResponse('Payroll adjustment not found');
        }

        $request->validate(['reason' => 'nullable|string|max:1000']);

        $model->delete();

        return $this->successResponse(null, 'Adjustment rejected and removed');
    }
}
