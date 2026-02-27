<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payroll\StorePayrollRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PayrollController extends BaseController
{
    use HasIndexQuery;

    protected PayrollService $payrollService;

    protected array $allowedSortColumns = ['id', 'company_id', 'month', 'year', 'status', 'locked_at', 'created_at'];

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Payroll::query()->with('company');
        $result = $this->indexQuery($request, $query, [], [
            'company_id' => 'company_id',
            'month' => 'month',
            'year' => 'year',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    public function store(StorePayrollRequest $request): JsonResponse
    {
        $validated = $request->validated();
        try {
            $payroll = $this->payrollService->generatePayroll(
                (int) $validated['company_id'],
                (int) $validated['month'],
                (int) $validated['year']
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($payroll->load('company')->load('details.employee'), 'Payroll generated successfully', 201);
    }

    public function show(string $payroll): JsonResponse
    {
        $model = Payroll::with(['company', 'details.employee'])->find($payroll);
        if (! $model) {
            return $this->notFoundResponse('Payroll not found');
        }

        return $this->successResponse($model);
    }

    public function update(Request $request, string $payroll): JsonResponse
    {
        $model = Payroll::find($payroll);
        if (! $model) {
            return $this->notFoundResponse('Payroll not found');
        }
        if ($model->status === 'locked') {
            return $this->errorResponse('Payroll is locked and cannot be updated', 422);
        }
        $model->update($request->only(['status']));

        $this->invalidatePayrollCache($model->company_id, $model->month, $model->year);

        return $this->successResponse($model->fresh(['company', 'details.employee']), 'Payroll updated successfully');
    }

    public function destroy(string $payroll): JsonResponse
    {
        $model = Payroll::find($payroll);
        if (! $model) {
            return $this->notFoundResponse('Payroll not found');
        }
        if ($model->status === 'locked') {
            return $this->errorResponse('Payroll is locked and cannot be deleted', 422);
        }
        $companyId = $model->company_id;
        $month = $model->month;
        $year = $model->year;
        $model->delete();

        $this->invalidatePayrollCache($companyId, $month, $year);

        return $this->successResponse(null, 'Payroll deleted successfully');
    }

    public function approve(string $id): JsonResponse
    {
        $payroll = Payroll::find($id);
        if (! $payroll) {
            return $this->notFoundResponse('Payroll not found');
        }
        try {
            $payroll = $this->payrollService->approvePayroll((int) $id);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
        $this->invalidatePayrollCache($payroll->company_id, $payroll->month, $payroll->year);

        return $this->successResponse($payroll->fresh(['company', 'details.employee']), 'Payroll approved successfully');
    }

    public function lock(string $id): JsonResponse
    {
        $payroll = Payroll::find($id);
        if (! $payroll) {
            return $this->notFoundResponse('Payroll not found');
        }
        try {
            $payroll = $this->payrollService->lockPayroll((int) $id);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
        $this->invalidatePayrollCache($payroll->company_id, $payroll->month, $payroll->year);

        return $this->successResponse($payroll->fresh(['company', 'details.employee']), 'Payroll locked successfully');
    }

    public function export(string $id): JsonResponse
    {
        $payroll = Payroll::with(['company', 'details.employee'])->find($id);
        if (! $payroll) {
            return $this->notFoundResponse('Payroll not found');
        }

        $export = [
            'payroll' => $payroll,
            'details' => $payroll->details->map(fn ($d) => [
                'employee_code' => $d->employee->code ?? null,
                'employee_name' => $d->employee->name ?? null,
                'base_salary' => $d->base_salary,
                'working_days' => $d->working_days,
                'overtime' => $d->overtime,
                'bonus' => $d->bonus,
                'allowance' => $d->allowance,
                'deduction' => $d->deduction,
                'fuel_cost' => $d->fuel_cost,
                'tax' => $d->tax,
                'net_salary' => $d->net_salary,
            ]),
        ];

        return $this->successResponse($export, 'OK');
    }

    public function mySalary(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;
        if (! $employee) {
            return $this->successResponse(null, 'No employee linked to your account');
        }
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $payroll = Payroll::whereHas('details', fn ($q) => $q->where('employee_id', $employee->id))
            ->where('month', $month)
            ->where('year', $year)
            ->with(['company', 'details' => fn ($q) => $q->where('employee_id', $employee->id)->with('employee')])
            ->first();

        if (! $payroll) {
            return $this->successResponse(null, 'No payroll found for this period');
        }

        return $this->successResponse($payroll);
    }

    private function invalidatePayrollCache(int $companyId, int $month, int $year): void
    {
        Cache::forget("payroll:{$companyId}:{$month}:{$year}");
    }
}
