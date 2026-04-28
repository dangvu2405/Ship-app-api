<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\PayrollAdjustment\RejectPayrollAdjustmentRequest;
use App\Http\Requests\PayrollAdjustment\StorePayrollAdjustmentRequest;
use App\Http\Requests\PayrollAdjustment\UpdatePayrollAdjustmentRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\PayrollAdjustment;
use App\Services\Payroll\PayrollAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

final class PayrollAdjustmentController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'company_id', 'driver_id', 'type', 'amount', 'created_at'];

    public function __construct(private readonly PayrollAdjustmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = PayrollAdjustment::query()->with(['driver', 'payroll', 'approver']);
        $result = $this->indexQuery($request, $query, ['reason'], [
            'driver_id' => 'driver_id',
            'payroll_id' => 'payroll_id',
            'type' => 'type',
            'company_id' => 'company_id',
        ]);

        return $this->successResponse($result, 'api.common.ok');
    }

    public function store(StorePayrollAdjustmentRequest $request): JsonResponse
    {
        try {
            $adjustment = $this->service->create($request->validated());

            return $this->successResponse($adjustment, 'api.payroll_adjustment.created', 201);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function show(string $id): JsonResponse
    {
        $model = PayrollAdjustment::with(['driver', 'payroll', 'approver'])->find($id);
        if (! $model) {
            return $this->notFoundResponse('api.payroll_adjustment.not_found');
        }

        return $this->successResponse($model, 'api.common.ok');
    }

    public function update(UpdatePayrollAdjustmentRequest $request, string $id): JsonResponse
    {
        $model = PayrollAdjustment::find($id);
        if (! $model) {
            return $this->notFoundResponse('api.payroll_adjustment.not_found');
        }

        try {
            $updated = $this->service->update($model, $request->validated());

            return $this->successResponse($updated, 'api.payroll_adjustment.updated');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $model = PayrollAdjustment::find($id);
        if (! $model) {
            return $this->notFoundResponse('api.payroll_adjustment.not_found');
        }

        try {
            $this->service->delete($model);

            return $this->successResponse(null, 'api.payroll_adjustment.deleted');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $model = PayrollAdjustment::find($id);
        if (! $model) {
            return $this->notFoundResponse('api.payroll_adjustment.not_found');
        }

        try {
            $approved = $this->service->approve($model, $request->user());

            return $this->successResponse($approved, 'api.payroll_adjustment.approved');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function reject(RejectPayrollAdjustmentRequest $request, string $id): JsonResponse
    {
        $model = PayrollAdjustment::find($id);
        if (! $model) {
            return $this->notFoundResponse('api.payroll_adjustment.not_found');
        }

        try {
            $this->service->delete($model);

            return $this->successResponse(null, 'api.payroll_adjustment.rejected_removed');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }
}
