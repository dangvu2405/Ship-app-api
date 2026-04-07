<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payroll;
use Exception;
use Illuminate\Support\Facades\Cache;

class PayrollWorkflowService
{
    public function __construct(private readonly PayrollService $payrollService) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function update(Payroll $payroll, array $payload): Payroll
    {
        if ($payroll->status === 'locked') {
            throw new Exception('Payroll is locked and cannot be updated');
        }

        $payroll->update($payload);
        $this->invalidatePayrollCache((int) $payroll->company_id, (int) $payroll->month, (int) $payroll->year);

        return $payroll;
    }

    public function delete(Payroll $payroll): void
    {
        if ($payroll->status === 'locked') {
            throw new Exception('Payroll is locked and cannot be deleted');
        }

        $companyId = (int) $payroll->company_id;
        $month = (int) $payroll->month;
        $year = (int) $payroll->year;

        $payroll->delete();
        $this->invalidatePayrollCache($companyId, $month, $year);
    }

    public function approve(int $payrollId): Payroll
    {
        $payroll = $this->payrollService->approvePayroll($payrollId);
        $this->invalidatePayrollCache((int) $payroll->company_id, (int) $payroll->month, (int) $payroll->year);

        return $payroll;
    }

    public function lock(int $payrollId): Payroll
    {
        $payroll = $this->payrollService->lockPayroll($payrollId);
        $this->invalidatePayrollCache((int) $payroll->company_id, (int) $payroll->month, (int) $payroll->year);

        return $payroll;
    }

    private function invalidatePayrollCache(int $companyId, int $month, int $year): void
    {
        Cache::forget("payroll:{$companyId}:{$month}:{$year}");
    }
}
