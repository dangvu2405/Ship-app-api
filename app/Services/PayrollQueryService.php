<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payroll;
use App\Models\User;

class PayrollQueryService
{
    public function findByIdForExport(int $payrollId): ?Payroll
    {
        return Payroll::with(['company', 'details.employee'])->find($payrollId);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildExportPayload(Payroll $payroll): array
    {
        return [
            'payroll' => $payroll,
            'details' => $payroll->details->map(fn ($detail): array => [
                'employee_code' => $detail->employee->code ?? null,
                'employee_name' => $detail->employee->name ?? null,
                'base_salary' => $detail->base_salary,
                'working_days' => $detail->working_days,
                'overtime' => $detail->overtime,
                'bonus' => $detail->bonus,
                'allowance' => $detail->allowance,
                'deduction' => $detail->deduction,
                'fuel_cost' => $detail->fuel_cost,
                'tax' => $detail->tax,
                'net_salary' => $detail->net_salary,
            ]),
        ];
    }

    public function findMySalary(User $user, int $month, int $year): ?Payroll
    {
        $employee = $user->employee;
        if (! $employee) {
            return null;
        }

        return Payroll::whereHas('details', fn ($query) => $query->where('employee_id', $employee->id))
            ->where('month', $month)
            ->where('year', $year)
            ->with([
                'company',
                'details' => fn ($query) => $query
                    ->where('employee_id', $employee->id)
                    ->with('employee'),
            ])
            ->first();
    }
}
