<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payroll;
use InvalidArgumentException;

class PayrollService
{
    public function __construct(private readonly DriverPayrollCalculationService $calculationService) {}

    public function generatePayroll(int $companyId, int $month, int $year): Payroll
    {
        $existing = Payroll::query()
            ->where('company_id', $companyId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existing && $existing->isLocked()) {
            throw new InvalidArgumentException('Payroll is locked and cannot be recalculated.', 403);
        }

        $result = $this->calculationService->createOrRecalculateDraft($companyId, $month, $year);

        return $result['payroll'];
    }
}
