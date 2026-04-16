<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollLine;
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

    /**
     * Lay bang luong theo tung tai xe va tung thang.
     *
     * @return array<string, mixed>|null
     */
    public function getDriverMonthlyPayroll(int $driverId, int $month, int $year): ?array
    {
        $line = PayrollLine::query()
            ->with(['driver', 'payroll.company'])
            ->where('driver_id', $driverId)
            ->whereHas('payroll', static function ($query) use ($month, $year): void {
                $query->where('month', $month)->where('year', $year);
            })
            ->first();

        if ($line === null) {
            return null;
        }

        return [
            'driver_id' => $line->driver_id,
            'month' => $month,
            'year' => $year,
            'payroll_id' => $line->payroll_id,
            'company_id' => $line->payroll?->company_id,
            'status' => $line->payroll?->status,
            'line' => $line,
        ];
    }
}
