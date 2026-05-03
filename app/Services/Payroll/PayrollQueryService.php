<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\User;

class PayrollQueryService
{
    public function findByIdForExport(int $id): ?Payroll
    {
        return Payroll::query()
            ->with(['company', 'lines.driver'])
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildExportPayload(Payroll $payroll): array
    {
        $payroll->loadMissing(['company', 'lines.driver']);

        return [
            'payroll' => $payroll,
            'summary' => [
                'drivers_count' => $payroll->lines->pluck('driver_id')->filter()->unique()->count(),
                'total_net_salary' => $payroll->lines->sum('net_salary'),
            ],
        ];
    }

    public function findMySalary(User $user, int $month, int $year): ?PayrollLine
    {
        if ($user->driver_id === null) {
            return null;
        }

        return PayrollLine::query()
            ->with(['payroll.company', 'driver'])
            ->where('driver_id', $user->driver_id)
            ->whereHas('payroll', static function ($q) use ($month, $year): void {
                $q->where('month', $month)->where('year', $year);
            })
            ->first();
    }
}
