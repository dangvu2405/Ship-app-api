<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Payroll;
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
                'employees_count' => $payroll->lines->pluck('driver_id')->filter()->unique()->count(),
                'total_net_salary' => $payroll->lines->sum('net_salary'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMySalary(User $user, int $month, int $year): ?array
    {
        // Driver no longer links to user in the current domain model.
        // My-salary lookup by authenticated user is intentionally disabled.
        return null;
    }
}
